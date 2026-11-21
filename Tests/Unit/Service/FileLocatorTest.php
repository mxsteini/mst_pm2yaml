<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Tests\Unit\Service;

use MSTITB\MstPm2yaml\Service\FileLocator;
use MSTITB\MstPm2yaml\Service\FolderResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FileLocatorTest extends UnitTestCase
{
    protected bool $backupEnvironment = true;

    private string $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = realpath(sys_get_temp_dir()) . '/mst-pm2yaml-' . bin2hex(random_bytes(4));
        GeneralUtility::mkdir_deep($this->project . '/packages/my_ext/Configuration/Powermail');
        GeneralUtility::mkdir_deep($this->project . '/vendor/acme/vendor_ext/Configuration/Powermail');
        GeneralUtility::mkdir_deep($this->project . '/public/fileadmin/_Powermail');
        $this->setContext('Development');
    }

    protected function tearDown(): void
    {
        @chmod($this->project . '/public/fileadmin/_Powermail', 0775);
        @chmod($this->project . '/packages/my_ext/Configuration/Powermail', 0775);
        GeneralUtility::rmdir($this->project, true);
        parent::tearDown();
    }

    public static function slugProvider(): \Generator
    {
        yield 'Umlaute' => ['Bewerbung für die Testphase', '-', 'bewerbung-fuer-die-testphase'];
        yield 'Unterstrich' => ['E-Mail Adresse', '_', 'e_mail_adresse'];
        yield 'ß und Satzzeichen' => ['Größe?!', '-', 'groesse'];
        yield 'nur Sonderzeichen' => ['!!!', '-', ''];
        yield 'leer' => ['', '-', ''];
    }

    #[Test]
    #[DataProvider('slugProvider')]
    public function slug(string $text, string $separator, string $expected): void
    {
        self::assertSame($expected, FileLocator::slug($text, $separator));
    }

    #[Test]
    public function absolutePathOfVersionedFile(): void
    {
        self::assertSame(
            $this->project . '/packages/my_ext/Configuration/Powermail/kontakt.yaml',
            $this->locator()->absolutePath('EXT:my_ext/Configuration/Powermail/kontakt.yaml', 87)
        );
    }

    #[Test]
    public function absolutePathOfFolderFile(): void
    {
        self::assertSame(
            $this->project . '/public/fileadmin/_Powermail/kontakt.yml',
            $this->locator()->absolutePath('fileadmin/_Powermail/kontakt.yml', 87)
        );
    }

    public static function invalidValueProvider(): \Generator
    {
        yield 'Pfad-Traversal in EXT' => ['EXT:my_ext/Configuration/Powermail/../../../../etc/passwd'];
        yield 'EXT ohne Powermail-Ordner' => ['EXT:my_ext/Configuration/kontakt.yaml'];
        yield 'EXT Unterordner' => ['EXT:my_ext/Configuration/Powermail/sub/kontakt.yaml'];
        yield 'EXT nicht geladen' => ['EXT:other_ext/Configuration/Powermail/kontakt.yaml'];
        yield 'EXT keine YAML-Datei' => ['EXT:my_ext/Configuration/Powermail/kontakt.php'];
        yield 'EXT versteckte Datei' => ['EXT:my_ext/Configuration/Powermail/.yaml'];
        yield 'Ordner Pfad-Traversal' => ['fileadmin/_Powermail/../../../etc/passwd.yaml'];
        yield 'Ordner Unterordner' => ['fileadmin/_Powermail/sub/kontakt.yaml'];
        yield 'anderer Ordner' => ['fileadmin/anderes/kontakt.yaml'];
        yield 'absoluter Pfad' => ['/etc/kontakt.yaml'];
        yield 'Ordner keine YAML-Datei' => ['fileadmin/_Powermail/kontakt.txt'];
        yield 'Ordner .htaccess' => ['fileadmin/_Powermail/.htaccess'];
    }

    #[Test]
    #[DataProvider('invalidValueProvider')]
    public function invalidValuesAreRejected(string $value): void
    {
        $locator = $this->locator();
        self::assertNull($locator->absolutePath($value, 87));
        self::assertFalse($locator->isWritable($value, 87));
    }

    #[Test]
    public function folderFilesAreAlwaysWritable(): void
    {
        $this->setContext('Production');
        self::assertTrue($this->locator()->isWritable('fileadmin/_Powermail/kontakt.yaml', 87));
    }

    #[Test]
    public function versionedFilesAreWritableInDevelopment(): void
    {
        self::assertTrue($this->locator()->isWritable('EXT:my_ext/Configuration/Powermail/kontakt.yaml', 87));
    }

    #[Test]
    public function versionedFilesAreReadOnlyInProduction(): void
    {
        $this->setContext('Production');
        self::assertFalse($this->locator()->isWritable('EXT:my_ext/Configuration/Powermail/kontakt.yaml', 87));
    }

    #[Test]
    public function versionedFilesAreReadOnlyOnStaging(): void
    {
        $this->setContext('Production/Staging');
        self::assertFalse($this->locator()->isWritable('EXT:my_ext/Configuration/Powermail/kontakt.yaml', 87));
    }

    #[Test]
    public function versionedFilesInVendorAreReadOnlyEvenInDevelopment(): void
    {
        self::assertFalse($this->locator()->isWritable('EXT:vendor_ext/Configuration/Powermail/kontakt.yaml', 87));
    }

    #[Test]
    public function missingPowermailFolderIsCreatedLater(): void
    {
        rmdir($this->project . '/packages/my_ext/Configuration/Powermail');
        self::assertTrue($this->locator()->isWritable('EXT:my_ext/Configuration/Powermail/kontakt.yaml', 87));
    }

    #[Test]
    public function readOnlyDirectoryIsNotWritable(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('root darf überall schreiben');
        }
        chmod($this->project . '/packages/my_ext/Configuration/Powermail', 0555);
        self::assertFalse($this->locator()->isWritable('EXT:my_ext/Configuration/Powermail/kontakt.yaml', 87));
    }

    #[Test]
    public function isFolderFile(): void
    {
        $locator = $this->locator();
        self::assertTrue($locator->isFolderFile('fileadmin/_Powermail/a.yaml'));
        self::assertFalse($locator->isFolderFile('EXT:my_ext/Configuration/Powermail/a.yaml'));
        self::assertFalse($locator->isFolderFile(''));
    }

    #[Test]
    public function listFilesShowsOnlyYamlFiles(): void
    {
        touch($this->project . '/packages/my_ext/Configuration/Powermail/kontakt.yaml');
        touch($this->project . '/packages/my_ext/Configuration/Powermail/readme.md');
        touch($this->project . '/public/fileadmin/_Powermail/newsletter.yml');
        touch($this->project . '/public/fileadmin/_Powermail/.htaccess');
        mkdir($this->project . '/public/fileadmin/_Powermail/ordner.yaml');

        self::assertSame([
            ['value' => 'EXT:my_ext/Configuration/Powermail/kontakt.yaml', 'label' => 'kontakt.yaml (EXT:my_ext)'],
            ['value' => 'fileadmin/_Powermail/newsletter.yml', 'label' => 'newsletter.yml (fileadmin/_Powermail)'],
        ], $this->locator()->listFiles(87));
    }

    #[Test]
    public function listFilesWithoutFolderIsEmpty(): void
    {
        rmdir($this->project . '/public/fileadmin/_Powermail');
        self::assertSame([], $this->locator()->listFiles(87));
    }

    #[Test]
    public function newFolderValueAvoidsExistingFiles(): void
    {
        $locator = $this->locator();
        self::assertSame('fileadmin/_Powermail/kontakt-formular.yaml', $locator->newFolderValue('Kontakt Formular', 87));
        touch($this->project . '/public/fileadmin/_Powermail/kontakt-formular.yaml');
        touch($this->project . '/public/fileadmin/_Powermail/kontakt-formular-2.yaml');
        self::assertSame('fileadmin/_Powermail/kontakt-formular-3.yaml', $locator->newFolderValue('Kontakt Formular', 87));
    }

    #[Test]
    public function newFolderValueForTitleWithoutLetters(): void
    {
        self::assertSame('fileadmin/_Powermail/formular.yaml', $this->locator()->newFolderValue('???', 87));
    }

    private function locator(): FileLocator
    {
        $package = $this->createStub(PackageInterface::class);
        $package->method('getPackageKey')->willReturn('my_ext');
        $package->method('getPackagePath')->willReturn($this->project . '/packages/my_ext/');
        $vendorPackage = $this->createStub(PackageInterface::class);
        $vendorPackage->method('getPackageKey')->willReturn('vendor_ext');
        $vendorPackage->method('getPackagePath')->willReturn($this->project . '/vendor/acme/vendor_ext/');
        $packages = ['my_ext' => $package, 'vendor_ext' => $vendorPackage];

        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('getActivePackages')->willReturn($packages);
        $packageManager->method('isPackageActive')->willReturnCallback(static fn(string $key): bool => isset($packages[$key]));
        $packageManager->method('getPackage')->willReturnCallback(static fn(string $key): PackageInterface => $packages[$key]);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willThrowException(new SiteNotFoundException('nix', 1));
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn('fileadmin/_Powermail/');

        return new FileLocator($packageManager, new FolderResolver($siteFinder, $extensionConfiguration));
    }

    private function setContext(string $context): void
    {
        Environment::initialize(
            new ApplicationContext($context),
            true,
            true,
            $this->project,
            $this->project . '/public',
            $this->project . '/var',
            $this->project . '/config',
            $this->project . '/vendor/bin/typo3',
            'UNIX'
        );
    }
}
