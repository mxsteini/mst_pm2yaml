<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Form\FormDataProvider;

use MSTITB\MstPm2yaml\Service\FormYamlService;
use MSTITB\MstPm2yaml\Service\Records;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Bearbeiten im Backend: vor dem Laden des Datensatzes die YAML-Datei übernehmen, wenn sie neuer ist.
 * Gilt für das Powermail-Plugin (tt_content) sowie Formular, Seiten und Felder.
 */
final class SyncBeforeEdit implements FormDataProviderInterface
{
    public function __construct(private readonly FormYamlService $service) {}

    public function addData(array $result): array
    {
        $uid = (int)($result['vanillaUid'] ?? 0);
        if (($result['command'] ?? '') !== 'edit' || $uid <= 0) {
            return $result;
        }
        $table = $result['tableName'] ?? '';
        if ($table === 'tt_content') {
            $row = BackendUtility::getRecord('tt_content', $uid, 'uid,CType,pi_flexform');
            if ($row !== null) {
                $this->service->syncForPlugin($row);
            }
        } elseif (in_array($table, Records::TABLES, true)) {
            $formUid = $this->service->defaultFormUid($table, $uid);
            if ($formUid !== null) {
                $this->service->syncIfOutdated($formUid);
            }
        }
        return $result;
    }
}
