<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\EventListener;

use MSTITB\MstPm2yaml\Service\FormYamlService;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Seitenmodul: vor der Vorschau des Powermail-Plugins die YAML-Datei übernehmen, wenn sie neuer ist.
 */
#[AsEventListener(identifier: 'mst-pm2yaml/sync-before-page-preview')]
final class SyncBeforePagePreview
{
    public function __construct(private readonly FormYamlService $service) {}

    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        if ($event->getTable() === 'tt_content') {
            $this->service->syncForPlugin($event->getRecord());
        }
    }
}
