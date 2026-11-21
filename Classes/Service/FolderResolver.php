<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Nicht versionierter Ordner für YAML-Dateien, relativ zum public-Verzeichnis:
 *   1. Site-Set-Einstellung mstPm2yaml.folder der Site, in der das Formular liegt
 *   2. Extension-Konfiguration (ext_conf_template.txt, überschreibbar in settings.php/additional.php)
 *   3. fileadmin/_Powermail/
 */
final class FolderResolver
{
    public const DEFAULT_FOLDER = 'fileadmin/_Powermail/';

    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function relativeFolder(int $pid): string
    {
        $folder = '';
        if ($pid > 0) {
            try {
                $folder = (string)$this->siteFinder->getSiteByPageId($pid)->getSettings()->get('mstPm2yaml.folder', '');
            } catch (SiteNotFoundException) {
            }
        }
        if (trim($folder) === '') {
            try {
                $folder = (string)$this->extensionConfiguration->get('mst_pm2yaml', 'folder');
            } catch (\Throwable) {
                $folder = '';
            }
        }
        if (trim($folder) === '') {
            $folder = self::DEFAULT_FOLDER;
        }
        return trim(trim($folder), '/') . '/';
    }

    public function absoluteFolder(int $pid): string
    {
        return Environment::getPublicPath() . '/' . $this->relativeFolder($pid);
    }
}
