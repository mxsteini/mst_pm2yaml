<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Tca;

use MSTITB\MstPm2yaml\Service\FileLocator;

/**
 * Auswahl der YAML-Dateien: EXT:*\/Configuration/Powermail/*.yaml und der Ordner der Site.
 */
final class FileItems
{
    public function __construct(private readonly FileLocator $fileLocator) {}

    public function addItems(array &$params): void
    {
        $pid = (int)($params['effectivePid'] ?? $params['row']['pid'] ?? 0);
        foreach ($this->fileLocator->listFiles($pid) as $item) {
            $params['items'][] = ['label' => $item['label'], 'value' => $item['value']];
        }
    }
}
