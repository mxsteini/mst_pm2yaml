<?php

use MSTITB\MstPm2yaml\Controller\ExportController;

return [
    'mst_pm2yaml_export' => [
        'path' => '/mst-pm2yaml/export',
        'target' => ExportController::class . '::exportAction',
    ],
];
