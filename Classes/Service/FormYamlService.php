<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abgleich zwischen Powermail-Formular und YAML-Datei.
 *
 *   Datei → DB   syncIfOutdated(): bevor das Plugin angezeigt wird (Frontend, Seitenmodul, Bearbeiten), wenn sich
 *                                  die Datei seit dem letzten Abgleich geändert hat (mtime ≠ tx_mstpm2yaml_synced)
 *                importFromFile(): beim Auswählen einer Datei, per pm2yaml:sync (Deployment)
 *   DB → Datei   writeBack():      beim Speichern im Backend, wenn die Datei schreibbar ist
 *                exportTo():       Export-Button bzw. pm2yaml:export, verknüpft das Formular mit der Datei
 *
 * Gesperrt (isLocked) ist ein Formular, dessen Datei nicht geschrieben werden darf: versionierte Datei
 * (EXT:…) außerhalb des Development-Kontexts. Dann ist die Datei die einzige Quelle.
 */
final class FormYamlService
{
    public function __construct(
        private readonly Records $records,
        private readonly FileLocator $fileLocator,
        private readonly FormExporter $exporter,
        private readonly FormImporter $importer,
        private readonly CacheManager $cacheManager,
        private readonly LockFactory $lockFactory,
        private readonly FlexFormService $flexFormService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Übernimmt die Datei, wenn sie sich seit dem letzten Abgleich geändert hat. Fehler werden nur geloggt:
     * Powermail arbeitet dann mit dem Stand der Datenbank weiter.
     *
     * @return bool ob importiert wurde
     */
    public function syncIfOutdated(int $formUid): bool
    {
        if (!$this->isOutdated($formUid)) {
            return false;
        }
        $lock = $this->lockFactory->createLocker('mst_pm2yaml_' . $formUid, LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE);
        try {
            $lock->acquire();
            // Ein paralleler Request kann inzwischen importiert haben
            if (!$this->isOutdated($formUid)) {
                return false;
            }
            $this->importFromFile($formUid);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('mst_pm2yaml: Formular {uid} nicht abgeglichen: {message}', ['uid' => $formUid, 'message' => $e->getMessage()]);
            return false;
        } finally {
            $lock->release();
        }
    }

    /**
     * Abgleich für ein Powermail-Plugin (tt_content-Zeile mit pi_flexform).
     */
    public function syncForPlugin(array $contentRow): void
    {
        if (($contentRow['CType'] ?? '') !== 'powermail_pi1' || empty($contentRow['pi_flexform']) || !is_string($contentRow['pi_flexform'])) {
            return;
        }
        $flexForm = $this->flexFormService->convertFlexFormContentToArray($contentRow['pi_flexform']);
        foreach (explode(',', (string)($flexForm['settings']['flexform']['main']['form'] ?? '')) as $uid) {
            $formUid = (int)$uid > 0 ? $this->defaultFormUid(Records::FORM, (int)$uid) : null;
            if ($formUid !== null) {
                $this->syncIfOutdated($formUid);
            }
        }
    }

    private function isOutdated(int $formUid): bool
    {
        $form = $this->formRow($formUid);
        if ($form === null || $form[Records::FILE_COLUMN] === '') {
            return false;
        }
        $path = $this->fileLocator->absolutePath((string)$form[Records::FILE_COLUMN], (int)$form['pid']);
        if ($path === null) {
            return false;
        }
        clearstatcache(true, $path);
        $mtime = @filemtime($path);
        return $mtime !== false && $mtime !== (int)$form[Records::SYNCED_COLUMN];
    }

    private function markSynced(int $formUid, string $path): void
    {
        clearstatcache(true, $path);
        $this->records->update(Records::FORM, $formUid, [Records::SYNCED_COLUMN => (int)@filemtime($path)]);
    }

    public function formRow(int $formUid): ?array
    {
        $form = $this->records->byUid(Records::FORM, $formUid);
        if ($form === null || (int)$form['t3ver_wsid'] !== 0 || (int)$form['sys_language_uid'] !== 0) {
            return null;
        }
        return $form;
    }

    public function defaultFormUid(string $table, int $uid): ?int
    {
        return $this->records->defaultFormUid($table, $uid);
    }

    public function fileValue(int $formUid): string
    {
        return (string)($this->formRow($formUid)[Records::FILE_COLUMN] ?? '');
    }

    public function isLocked(int $formUid): bool
    {
        $form = $this->formRow($formUid);
        if ($form === null || $form[Records::FILE_COLUMN] === ''
            || !$this->isValidFile((string)$form[Records::FILE_COLUMN], (int)$form['pid'])
        ) {
            return false;
        }
        return !$this->fileLocator->isWritable((string)$form[Records::FILE_COLUMN], (int)$form['pid']);
    }

    /**
     * Gültiger Wert für das Feld tx_mstpm2yaml_file: leer oder eine vorhandene Datei, die zur Auswahl gehört.
     */
    public function isValidFile(string $value, int $pid): bool
    {
        if ($value === '') {
            return true;
        }
        $path = $this->fileLocator->absolutePath($value, $pid);
        return $path !== null && is_file($path);
    }

    public function isWritable(int $formUid): bool
    {
        $form = $this->formRow($formUid);
        return $form !== null && $form[Records::FILE_COLUMN] !== ''
            && $this->fileLocator->isWritable((string)$form[Records::FILE_COLUMN], (int)$form['pid']);
    }

    /**
     * @return list<array<string, mixed>> Formulare (Standardsprache, live) mit verknüpfter Datei
     */
    public function linkedForms(): array
    {
        $forms = [];
        foreach ($this->records->live(Records::FORM, ['sys_language_uid' => 0]) as $form) {
            if ($form[Records::FILE_COLUMN] !== '') {
                $forms[] = $form;
            }
        }
        return $forms;
    }

    /**
     * @return bool ob sich in der Datenbank etwas geändert hat
     */
    public function importFromFile(int $formUid, bool $dryRun = false): bool
    {
        $form = $this->requireForm($formUid);
        $path = $this->path($form);
        if (!is_file($path)) {
            throw new \RuntimeException('Datei fehlt: ' . $form[Records::FILE_COLUMN], 1795250401);
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            throw new \RuntimeException($form[Records::FILE_COLUMN] . ': ' . $e->getMessage(), 1795250402, $e);
        }
        if (!is_array($data)) {
            throw new \RuntimeException($form[Records::FILE_COLUMN] . ': kein Formular', 1795250403);
        }

        $connection = $this->records->connection();
        $connection->beginTransaction();
        try {
            $changed = $this->importer->import($formUid, $data);
            if ($dryRun) {
                $connection->rollBack();
            } else {
                $this->markSynced($formUid, $path);
                $connection->commit();
            }
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw new \RuntimeException($form[Records::FILE_COLUMN] . ': ' . $e->getMessage(), 1795250404, $e);
        }
        if ($changed && !$dryRun) {
            $this->cacheManager->flushCachesInGroup('pages');
        }
        return $changed;
    }

    /**
     * @return bool ob die Datei geschrieben wurde (false: nicht verknüpft, nicht schreibbar oder unverändert)
     */
    public function writeBack(int $formUid): bool
    {
        if (!$this->isWritable($formUid)) {
            return false;
        }
        return $this->writeFile($this->requireForm($formUid));
    }

    /**
     * Schreibt das Formular in eine Datei und verknüpft es damit.
     *
     * @param string|null $value Ziel (EXT:… oder Ordner-Datei), null: neue Datei im Ordner der Site
     * @return string Wert im Feld tx_mstpm2yaml_file
     */
    public function exportTo(int $formUid, ?string $value = null): string
    {
        $form = $this->requireForm($formUid);
        $value ??= $this->fileLocator->newFolderValue((string)$form['title'], (int)$form['pid']);
        if (!$this->fileLocator->isWritable($value, (int)$form['pid'])) {
            throw new \RuntimeException('Nicht schreibbar: ' . $value
                . ' (versionierte Dateien nur im Development-Kontext, Ordner-Dateien nur im Ordner der Site)', 1795250405);
        }
        $form[Records::FILE_COLUMN] = $value;
        $this->writeFile($form);
        $this->records->update(Records::FORM, $formUid, [Records::FILE_COLUMN => $value, 'tstamp' => time()]);
        return $value;
    }

    /**
     * Setzt die verknüpfte Datei, ohne abzugleichen (z. B. nach einem gescheiterten Import zurück).
     */
    public function relink(int $formUid, string $value): void
    {
        $this->records->update(Records::FORM, $formUid, [Records::FILE_COLUMN => $value]);
    }

    private function writeFile(array $form): bool
    {
        $path = $this->path($form);
        $yaml = '# Powermail-Formular (mst_pm2yaml): Seiten über ihren Bezeichner, Felder über ihren Marker.' . LF
            . '# Standardwerte fehlen. Übersetzungen (localizations: <Sprach-ID>) enthalten nur Abweichungen.' . LF
            . Yaml::dump($this->exporter->build((int)$form['uid']), 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        if (is_file($path) && file_get_contents($path) === $yaml) {
            $this->markSynced((int)$form['uid'], $path);
            return false;
        }
        $directory = dirname($path);
        if (!is_dir($directory)) {
            GeneralUtility::mkdir_deep($directory);
        }
        $this->protectFolder($directory);
        if (!GeneralUtility::writeFile($path, $yaml, true)) {
            throw new \RuntimeException('Konnte nicht schreiben: ' . $path, 1795250406);
        }
        $this->markSynced((int)$form['uid'], $path);
        return true;
    }

    /**
     * Ordner unter public/ (fileadmin) sind per HTTP erreichbar.
     */
    private function protectFolder(string $directory): void
    {
        $htaccess = $directory . '/.htaccess';
        if (str_starts_with($directory, Environment::getPublicPath() . '/') && !is_file($htaccess)) {
            GeneralUtility::writeFile($htaccess, '# mst_pm2yaml: YAML-Dateien nicht ausliefern' . LF . 'Require all denied' . LF, true);
        }
    }

    private function path(array $form): string
    {
        return $this->fileLocator->absolutePath((string)$form[Records::FILE_COLUMN], (int)$form['pid'])
            ?? throw new \RuntimeException('Ungültige Datei für Formular ' . $form['uid'] . ': ' . $form[Records::FILE_COLUMN]
                . ' (EXT:<ext>/Configuration/Powermail/<name>.yaml oder im Ordner der Site)', 1795250407);
    }

    private function requireForm(int $formUid): array
    {
        return $this->formRow($formUid)
            ?? throw new \RuntimeException('Formular ' . $formUid . ' nicht gefunden (Standardsprache, live)', 1795250408);
    }
}
