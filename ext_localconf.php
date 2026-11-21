<?php

declare(strict_types=1);

use MSTITB\MstPm2yaml\Form\FieldControl\ExportControl;
use MSTITB\MstPm2yaml\Form\FormDataProvider\LockFileForm;
use MSTITB\MstPm2yaml\Form\FormDataProvider\SyncBeforeEdit;
use MSTITB\MstPm2yaml\Hooks\DataHandlerHook;
use TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseEditRow;
use TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['mst_pm2yaml']
    = DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['mst_pm2yaml']
    = DataHandlerHook::class;

// Datei übernehmen, bevor der Datensatz geladen wird
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][SyncBeforeEdit::class] = [
    'before' => [DatabaseEditRow::class],
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][LockFileForm::class] = [
    'depends' => [InitializeProcessedTca::class],
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1795250000] = [
    'nodeName' => 'mstPm2yamlExport',
    'priority' => 30,
    'class' => ExportControl::class,
];
