<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Form\FormDataProvider;

use MSTITB\MstPm2yaml\Service\FormYamlService;
use MSTITB\MstPm2yaml\Service\Records;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Gesperrte Formulare im Backend schreibgeschützt anzeigen (alle Felder außer der Dateiauswahl) und am
 * Formular den Zustand der Verknüpfung erklären.
 */
final class LockFileForm implements FormDataProviderInterface
{
    public function __construct(private readonly FormYamlService $service) {}

    public function addData(array $result): array
    {
        $table = $result['tableName'] ?? '';
        $uid = $result['databaseRow']['uid'] ?? null;
        if (!in_array($table, Records::TABLES, true) || !MathUtility::canBeInterpretedAsInteger($uid)) {
            return $result;
        }
        $formUid = $this->service->defaultFormUid($table, (int)$uid);
        if ($formUid === null) {
            return $result;
        }
        $locked = $this->service->isLocked($formUid);
        $file = $this->service->fileValue($formUid);

        if ($table === Records::FORM && isset($result['processedTca']['columns'][Records::FILE_COLUMN]) && $file !== '') {
            $result['processedTca']['columns'][Records::FILE_COLUMN]['description'] = $locked
                ? 'Schreibgeschützt: Das Formular wird aus der versionierten Datei gepflegt und bei jedem Deployment abgeglichen. Zum Bearbeiten die Datei ändern oder hier „nur Datenbank“ wählen.'
                : 'Änderungen an Formular, Seiten und Feldern werden beim Speichern in die Datei geschrieben. Eine andere Datei auswählen übernimmt deren Inhalt.';
        }
        if (!$locked) {
            return $result;
        }
        foreach ($result['processedTca']['columns'] as $column => $_) {
            if ($table === Records::FORM && $column === Records::FILE_COLUMN) {
                continue;
            }
            $result['processedTca']['columns'][$column]['config']['readOnly'] = true;
        }
        return $result;
    }
}
