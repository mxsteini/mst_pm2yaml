<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\EventListener;

use In2code\Powermail\Events\FormControllerInitializeObjectEvent;
use MSTITB\MstPm2yaml\Service\FormYamlService;
use MSTITB\MstPm2yaml\Service\Records;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Frontend: vor jeder Action des Powermail-Plugins die YAML-Datei übernehmen, wenn sie neuer ist.
 */
#[AsEventListener(identifier: 'mst-pm2yaml/sync-before-frontend-plugin')]
final class SyncBeforeFrontendPlugin
{
    public function __construct(private readonly FormYamlService $service) {}

    public function __invoke(FormControllerInitializeObjectEvent $event): void
    {
        foreach (explode(',', (string)($event->getSettings()['main']['form'] ?? '')) as $uid) {
            $formUid = (int)$uid > 0 ? $this->service->defaultFormUid(Records::FORM, (int)$uid) : null;
            if ($formUid !== null) {
                $this->service->syncIfOutdated($formUid);
            }
        }
    }
}
