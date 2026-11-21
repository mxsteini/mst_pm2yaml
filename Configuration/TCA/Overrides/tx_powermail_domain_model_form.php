<?php

use MSTITB\MstPm2yaml\Tca\FileItems;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTCAcolumns('tx_powermail_domain_model_form', [
    'tx_mstpm2yaml_file' => [
        'label' => 'YAML-Datei',
        'description' => 'Formular, Seiten und Felder werden mit dieser Datei abgeglichen. Beim Auswählen wird der Inhalt der Datei übernommen.',
        'l10n_mode' => 'exclude',
        'l10n_display' => 'defaultAsReadonly',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => '– nur Datenbank –', 'value' => ''],
            ],
            'itemsProcFunc' => FileItems::class . '->addItems',
            'default' => '',
            'fieldControl' => [
                'mstPm2yamlExport' => [
                    'renderType' => 'mstPm2yamlExport',
                ],
            ],
        ],
    ],
]);
ExtensionManagementUtility::addToAllTCAtypes('tx_powermail_domain_model_form', 'tx_mstpm2yaml_file', '', 'after:title');
