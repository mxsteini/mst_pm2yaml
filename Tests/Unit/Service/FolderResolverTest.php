<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Tests\Unit\Service;

use MSTITB\MstPm2yaml\Service\FolderResolver;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FolderResolverTest extends UnitTestCase
{
    #[Test]
    public function siteSettingWins(): void
    {
        self::assertSame('fileadmin/site/', $this->resolver('fileadmin/site', 'fileadmin/global/')->relativeFolder(87));
    }

    #[Test]
    public function extensionConfigurationIsFallbackForEmptySiteSetting(): void
    {
        self::assertSame('fileadmin/global/', $this->resolver('', 'fileadmin/global')->relativeFolder(87));
        self::assertSame('fileadmin/global/', $this->resolver('   ', 'fileadmin/global')->relativeFolder(87));
    }

    #[Test]
    public function extensionConfigurationIsFallbackOutsideOfSites(): void
    {
        self::assertSame('fileadmin/global/', $this->resolver(null, 'fileadmin/global')->relativeFolder(87));
    }

    #[Test]
    public function pidZeroDoesNotAskForASite(): void
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->expects(self::never())->method('getSiteByPageId');
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn('fileadmin/global/');
        self::assertSame('fileadmin/global/', (new FolderResolver($siteFinder, $extensionConfiguration))->relativeFolder(0));
    }

    #[Test]
    public function defaultFolderIfNothingIsConfigured(): void
    {
        self::assertSame(FolderResolver::DEFAULT_FOLDER, $this->resolver(null, '')->relativeFolder(87));
    }

    #[Test]
    public function defaultFolderIfExtensionConfigurationIsMissing(): void
    {
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willThrowException(new SiteNotFoundException('nix', 1));
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willThrowException(new \RuntimeException('nicht konfiguriert'));
        self::assertSame(FolderResolver::DEFAULT_FOLDER, (new FolderResolver($siteFinder, $extensionConfiguration))->relativeFolder(87));
    }

    #[Test]
    public function slashesAndWhitespaceAreNormalized(): void
    {
        self::assertSame('fileadmin/x/', $this->resolver(' /fileadmin/x// ', '')->relativeFolder(87));
    }

    /**
     * @param string|null $siteFolder null: Formular liegt außerhalb jeder Site
     */
    private function resolver(?string $siteFolder, string $globalFolder): FolderResolver
    {
        $siteFinder = $this->createStub(SiteFinder::class);
        if ($siteFolder === null) {
            $siteFinder->method('getSiteByPageId')->willThrowException(new SiteNotFoundException('nix', 1));
        } else {
            $siteFinder->method('getSiteByPageId')->willReturn(
                new Site('test', 1, ['settings' => ['mstPm2yaml' => ['folder' => $siteFolder]]])
            );
        }
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($globalFolder);
        return new FolderResolver($siteFinder, $extensionConfiguration);
    }
}
