<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Form\FieldControl;

use MSTITB\MstPm2yaml\Service\Records;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Export-Button neben der Dateiauswahl, nur bei gespeicherten Formularen ohne Datei (im Live-Workspace).
 */
final class ExportControl extends AbstractNode
{
    public function __construct(private readonly UriBuilder $uriBuilder) {}

    public function render(): array
    {
        $uid = $this->data['databaseRow']['uid'] ?? null;
        $value = $this->data['databaseRow'][Records::FILE_COLUMN] ?? '';
        $value = is_array($value) ? (string)($value[0] ?? '') : (string)$value;
        if ($this->data['tableName'] !== Records::FORM || !MathUtility::canBeInterpretedAsInteger($uid)
            || (int)($this->data['databaseRow']['sys_language_uid'][0] ?? $this->data['databaseRow']['sys_language_uid'] ?? 0) !== 0
            || $value !== '' || (int)$GLOBALS['BE_USER']->workspace !== 0
        ) {
            return [];
        }
        $editUrl = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [Records::FORM => [(int)$uid => 'edit']],
            'returnUrl' => $this->data['returnUrl'] ?? '',
        ]);
        return [
            'iconIdentifier' => 'actions-database-export',
            'title' => 'Als YAML-Datei exportieren (ungespeicherte Änderungen gehen verloren)',
            'linkAttributes' => [
                'href' => (string)$this->uriBuilder->buildUriFromRoute('mst_pm2yaml_export', [
                    'form' => (int)$uid,
                    'returnUrl' => $editUrl,
                ]),
            ],
        ];
    }
}
