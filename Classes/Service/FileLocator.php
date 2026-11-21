<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * YAML-Dateien finden und prüfen. Werte im Feld tx_mstpm2yaml_file:
 *   EXT:<ext>/Configuration/Powermail/<name>.yaml   versioniert, schreibbar nur im Development-Kontext
 *   <ordner>/<name>.yaml                             aus dem Ordner der Site, immer schreibbar
 */
final class FileLocator
{
    private const EXT_PATTERN = '#^EXT:([a-z0-9_]+)/Configuration/Powermail/([A-Za-z0-9][A-Za-z0-9._-]*\.ya?ml)$#';
    private const NAME_PATTERN = '#^[A-Za-z0-9][A-Za-z0-9._-]*\.ya?ml$#';

    public function __construct(
        private readonly PackageManager $packageManager,
        private readonly FolderResolver $folderResolver,
    ) {}

    /**
     * @return list<array{value: string, label: string}>
     */
    public function listFiles(int $pid): array
    {
        $items = [];
        foreach ($this->packageManager->getActivePackages() as $package) {
            $directory = $package->getPackagePath() . 'Configuration/Powermail/';
            foreach ($this->yamlFiles($directory) as $name) {
                $items[] = [
                    'value' => 'EXT:' . $package->getPackageKey() . '/Configuration/Powermail/' . $name,
                    'label' => $name . ' (EXT:' . $package->getPackageKey() . ')',
                ];
            }
        }
        $folder = $this->folderResolver->relativeFolder($pid);
        foreach ($this->yamlFiles($this->folderResolver->absoluteFolder($pid)) as $name) {
            $items[] = ['value' => $folder . $name, 'label' => $name . ' (' . rtrim($folder, '/') . ')'];
        }
        return $items;
    }

    /**
     * Absoluter Pfad, null bei ungültigem Wert. Die Datei muss nicht existieren.
     */
    public function absolutePath(string $value, int $pid): ?string
    {
        if (str_starts_with($value, 'EXT:')) {
            if (!preg_match(self::EXT_PATTERN, $value, $matches) || !$this->packageManager->isPackageActive($matches[1])) {
                return null;
            }
            return $this->packageManager->getPackage($matches[1])->getPackagePath() . 'Configuration/Powermail/' . $matches[2];
        }
        $folder = $this->folderResolver->relativeFolder($pid);
        if (!str_starts_with($value, $folder)) {
            return null;
        }
        $name = substr($value, strlen($folder));
        if (!preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }
        return $this->folderResolver->absoluteFolder($pid) . $name;
    }

    public function isFolderFile(string $value): bool
    {
        return $value !== '' && !str_starts_with($value, 'EXT:');
    }

    /**
     * Darf die Datei beim Speichern im Backend geschrieben werden?
     * Ordner-Dateien immer, versionierte Dateien nur im Development-Kontext und außerhalb von vendor/.
     */
    public function isWritable(string $value, int $pid): bool
    {
        $path = $this->absolutePath($value, $pid);
        if ($path === null) {
            return false;
        }
        if ($this->isFolderFile($value)) {
            return true;
        }
        if (!Environment::getContext()->isDevelopment()) {
            return false;
        }
        // Nächster existierender Pfad (Configuration/Powermail/ wird beim Schreiben angelegt)
        $existing = $path;
        while (!file_exists($existing) && dirname($existing) !== $existing) {
            $existing = dirname($existing);
        }
        $real = realpath($existing);
        if ($real === false || str_starts_with($real, Environment::getProjectPath() . '/vendor/')) {
            return false;
        }
        return is_writable($existing);
    }

    /**
     * Neuer, noch nicht vergebener Dateiname im Ordner der Site.
     */
    public function newFolderValue(string $title, int $pid): string
    {
        $folder = $this->folderResolver->relativeFolder($pid);
        $base = self::slug($title, '-') ?: 'formular';
        $name = $base . '.yaml';
        for ($i = 2; is_file($this->folderResolver->absoluteFolder($pid) . $name); $i++) {
            $name = $base . '-' . $i . '.yaml';
        }
        return $folder . $name;
    }

    public static function slug(string $text, string $separator): string
    {
        $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
        if (function_exists('transliterator_transliterate')) {
            $text = (string)transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        }
        $text = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', $separator, $text));
        return trim($text, $separator);
    }

    /**
     * @return list<string>
     */
    private function yamlFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $names = [];
        foreach (scandir($directory) ?: [] as $name) {
            if (preg_match(self::NAME_PATTERN, $name) && is_file($directory . $name)) {
                $names[] = $name;
            }
        }
        return $names;
    }
}
