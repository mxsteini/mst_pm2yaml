<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTCAcolumns('tx_powermail_domain_model_page', [
    'tx_mstpm2yaml_identifier' => [
        'label' => 'Bezeichner in der YAML-Datei',
        'description' => 'Wird beim Export aus dem Titel erzeugt, wenn leer.',
        'l10n_mode' => 'exclude',
        'l10n_display' => 'defaultAsReadonly',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 255,
            'eval' => 'trim,alphanum_x,lower',
            'default' => '',
        ],
    ],
]);
ExtensionManagementUtility::addToAllTCAtypes('tx_powermail_domain_model_page', 'tx_mstpm2yaml_identifier', '', 'after:title');
