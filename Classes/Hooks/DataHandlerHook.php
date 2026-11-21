<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Hooks;

use MSTITB\MstPm2yaml\Service\FormYamlService;
use MSTITB\MstPm2yaml\Service\Records;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * - Gesperrte Formulare (versionierte Datei außerhalb von Development): Änderungen an Formular, Seiten und
 *   Feldern verhindern, nur die Dateiauswahl darf geändert werden.
 * - Datei neu ausgewählt: Inhalt der Datei übernehmen.
 * - Sonst nach dem Speichern im Live-Workspace bzw. beim Veröffentlichen: Datei neu schreiben, wenn schreibbar.
 */
final class DataHandlerHook
{
    private const LOCKED_COMMANDS = ['delete', 'undelete', 'move', 'copy', 'localize', 'copyToLanguage', 'inlineLocalizeSynchronize'];

    /** @var array<string, string> Formular-ids (auch NEW…), deren Datei sich ändert => bisherige Datei */
    private array $fileChanged = [];

    public function __construct(
        private readonly FormYamlService $service,
        private readonly FlashMessageService $flashMessageService,
    ) {}

    public function processDatamap_preProcessFieldArray(?array &$incomingFieldArray, string $table, int|string $id, DataHandler $dataHandler): void
    {
        if ($incomingFieldArray === null || !in_array($table, Records::TABLES, true)) {
            return;
        }
        $isExisting = MathUtility::canBeInterpretedAsInteger($id);
        // Übersetzungen erben die Datei (l10n_mode exclude), maßgeblich ist das Formular der Standardsprache
        $isTranslation = $isExisting && $table === Records::FORM && $this->service->formRow((int)$id) === null;
        if ($isTranslation) {
            unset($incomingFieldArray[Records::FILE_COLUMN]);
        }
        if ($table === Records::FORM && !$isTranslation && array_key_exists(Records::FILE_COLUMN, $incomingFieldArray)) {
            $value = (string)$incomingFieldArray[Records::FILE_COLUMN];
            $current = $isExisting ? $this->service->fileValue((int)$id) : '';
            $pid = $isExisting ? (int)($this->service->formRow((int)$id)['pid'] ?? 0) : max(0, (int)($incomingFieldArray['pid'] ?? 0));
            if ($value !== $current && !$this->service->isValidFile($value, $pid)) {
                unset($incomingFieldArray[Records::FILE_COLUMN]);
                $text = 'Ungültige oder fehlende YAML-Datei: ' . $value;
                $dataHandler->log($table, $isExisting ? (int)$id : 0, 2, 0, 1, 'mst_pm2yaml: ' . $text);
                $this->message($text, ContextualFeedbackSeverity::ERROR);
            } elseif ($value !== $current) {
                $this->fileChanged[(string)$id] = $current;
            }
        }

        $formUid = $this->formUidForIncoming($table, $id, $incomingFieldArray);
        if ($formUid === null || !$this->service->isLocked($formUid)) {
            return;
        }
        if ($table === Records::FORM && (int)$id === $formUid && array_key_exists(Records::FILE_COLUMN, $incomingFieldArray)) {
            $incomingFieldArray = [Records::FILE_COLUMN => $incomingFieldArray[Records::FILE_COLUMN]];
            return;
        }
        $incomingFieldArray = null;
        $this->deny($dataHandler, $table, $id, $formUid);
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $forms = [];
        $previousFiles = [];
        foreach (Records::TABLES as $table) {
            foreach (array_keys($dataHandler->datamap[$table] ?? []) as $id) {
                $uid = MathUtility::canBeInterpretedAsInteger($id) ? (int)$id : (int)($dataHandler->substNEWwithIDs[$id] ?? 0);
                $formUid = $uid > 0 ? $this->service->defaultFormUid($table, $uid) : null;
                if ($formUid === null) {
                    continue;
                }
                if ($table === Records::FORM && isset($this->fileChanged[(string)$id])) {
                    $forms[$formUid] = 'import';
                    $previousFiles[$formUid] = $this->fileChanged[(string)$id];
                } else {
                    $forms[$formUid] ??= 'write';
                }
            }
        }
        $this->fileChanged = [];
        if ((int)$dataHandler->BE_USER->workspace !== 0) {
            return;
        }
        foreach ($forms as $formUid => $action) {
            $this->run($dataHandler, $formUid, $action, $previousFiles[$formUid] ?? null);
        }
    }

    /**
     * Befehle an Seiten und Feldern gesperrter Formulare verhindern (und Übersetzen des Formulars).
     */
    public function processCmdmap(string $command, string $table, int|string $id, mixed $value, bool &$commandIsProcessed, DataHandler $dataHandler): void
    {
        if (!in_array($table, Records::TABLES, true) || !in_array($command, self::LOCKED_COMMANDS, true)
            || !MathUtility::canBeInterpretedAsInteger($id)
        ) {
            return;
        }
        if ($table === Records::FORM && !in_array($command, ['localize', 'copyToLanguage', 'inlineLocalizeSynchronize'], true)) {
            return;
        }
        $formUid = $this->service->defaultFormUid($table, (int)$id);
        if ($formUid !== null && $this->service->isLocked($formUid)) {
            $commandIsProcessed = true;
            $this->deny($dataHandler, $table, $id, $formUid);
        }
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        $forms = [];
        $isLive = (int)$dataHandler->BE_USER->workspace === 0;
        foreach (Records::TABLES as $table) {
            foreach ($dataHandler->cmdmap[$table] ?? [] as $id => $commands) {
                foreach (array_keys((array)$commands) as $command) {
                    // Veröffentlichen schreibt live, auch aus einem Workspace heraus
                    if (!$isLive && $command !== 'version') {
                        continue;
                    }
                    $uids = [(int)$id];
                    if (isset($dataHandler->copyMappingArray_merged[$table][$id])) {
                        $uids[] = (int)$dataHandler->copyMappingArray_merged[$table][$id];
                    }
                    foreach ($uids as $uid) {
                        $formUid = $this->service->defaultFormUid($table, $uid);
                        if ($formUid !== null) {
                            $forms[$formUid] = 'write';
                        }
                    }
                }
            }
        }
        foreach (array_keys($forms) as $formUid) {
            $this->run($dataHandler, $formUid, 'write');
        }
    }

    /**
     * @param string|null $previousFile bei 'import': bisherige Datei, wird bei einem Fehler wiederhergestellt
     */
    private function run(DataHandler $dataHandler, int $formUid, string $action, ?string $previousFile = null): void
    {
        $file = $this->service->fileValue($formUid);
        if ($file === '') {
            return;
        }
        try {
            if ($action === 'import') {
                $this->service->importFromFile($formUid);
                $this->message('Formular aus ' . $file . ' übernommen.', ContextualFeedbackSeverity::OK);
            } elseif ($this->service->writeBack($formUid)) {
                $this->message('Formular nach ' . $file . ' geschrieben.', ContextualFeedbackSeverity::INFO);
            }
        } catch (\Throwable $e) {
            $text = $e->getMessage();
            if ($action === 'import' && $previousFile !== null) {
                // Ungültige Datei nicht verknüpft lassen, sonst scheitert jeder weitere Abgleich
                $this->service->relink($formUid, $previousFile);
                $text .= ' – Auswahl zurückgesetzt' . ($previousFile !== '' ? ' auf ' . $previousFile : '') . '.';
            }
            $dataHandler->log(Records::FORM, $formUid, 2, 0, 1, 'mst_pm2yaml: ' . $text);
            $this->message($text, ContextualFeedbackSeverity::ERROR);
        }
    }

    private function formUidForIncoming(string $table, int|string $id, array $fieldArray): ?int
    {
        if (MathUtility::canBeInterpretedAsInteger($id)) {
            return $this->service->defaultFormUid($table, (int)$id);
        }
        $parentColumn = ['tx_powermail_domain_model_page' => 'form', 'tx_powermail_domain_model_field' => 'page'][$table] ?? null;
        $parent = $parentColumn !== null ? ($fieldArray[$parentColumn] ?? null) : null;
        if ($parent === null || !MathUtility::canBeInterpretedAsInteger($parent)) {
            return null;
        }
        return $this->service->defaultFormUid($table === Records::PAGE ? Records::FORM : Records::PAGE, (int)$parent);
    }

    private function deny(DataHandler $dataHandler, string $table, int|string $id, int $formUid): void
    {
        $text = 'Formular ' . $formUid . ' wird aus ' . $this->service->fileValue($formUid)
            . ' gepflegt und ist hier schreibgeschützt (' . $table . ':' . $id . ').';
        $dataHandler->log($table, MathUtility::canBeInterpretedAsInteger($id) ? (int)$id : 0, 2, 0, 1, 'mst_pm2yaml: ' . $text);
        $this->message($text, ContextualFeedbackSeverity::WARNING);
    }

    private function message(string $text, ContextualFeedbackSeverity $severity): void
    {
        $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(
            new FlashMessage($text, 'Powermail ↔ YAML', $severity, !Environment::isCli())
        );
    }
}
