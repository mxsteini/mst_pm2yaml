<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Controller;

use MSTITB\MstPm2yaml\Service\FormYamlService;
use MSTITB\MstPm2yaml\Service\Records;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend-Route mst_pm2yaml_export: Formular in den Ordner der Site exportieren und damit verknüpfen.
 */
final class ExportController
{
    public function __construct(
        private readonly FormYamlService $service,
        private readonly FlashMessageService $flashMessageService,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function exportAction(ServerRequestInterface $request): ResponseInterface
    {
        $formUid = (int)($request->getQueryParams()['form'] ?? 0);
        $returnUrl = GeneralUtility::sanitizeLocalUrl((string)($request->getQueryParams()['returnUrl'] ?? ''))
            ?: (string)$this->uriBuilder->buildUriFromRoute('web_list');

        try {
            $form = $this->service->formRow($formUid)
                ?? throw new \RuntimeException('Formular ' . $formUid . ' nicht gefunden', 1795250501);
            $backendUser = $GLOBALS['BE_USER'];
            $page = BackendUtility::getRecord('pages', (int)$form['pid']) ?? [];
            if (!$backendUser->check('tables_modify', Records::FORM) || (int)$backendUser->workspace !== 0
                || ((int)$form['pid'] > 0 && !$backendUser->doesUserHaveAccess($page, Permission::CONTENT_EDIT))
            ) {
                throw new \RuntimeException('Keine Berechtigung (Export nur im Live-Workspace)', 1795250502);
            }
            if ($form[Records::FILE_COLUMN] !== '') {
                throw new \RuntimeException('Formular ist bereits mit ' . $form[Records::FILE_COLUMN] . ' verknüpft', 1795250503);
            }
            $value = $this->service->exportTo($formUid);
            $message = new FlashMessage('Exportiert nach ' . $value . '. Änderungen werden ab jetzt beim Speichern in die Datei geschrieben.', 'Powermail ↔ YAML', ContextualFeedbackSeverity::OK, true);
        } catch (\Throwable $e) {
            $message = new FlashMessage($e->getMessage(), 'Powermail ↔ YAML', ContextualFeedbackSeverity::ERROR, true);
        }
        $this->flashMessageService->getMessageQueueByIdentifier()->enqueue($message);
        return new RedirectResponse($returnUrl);
    }
}
