<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Tests\Unit\Service;

use MSTITB\MstPm2yaml\Service\Records;
use MSTITB\MstPm2yaml\Tests\Unit\Fixtures\ColumnsFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ColumnsTest extends UnitTestCase
{
    public static function normalizeProvider(): \Generator
    {
        yield 'int aus String' => ['mandatory', '5', 5];
        yield 'int aus true' => ['mandatory', true, 1];
        yield 'int aus false' => ['mandatory', false, 0];
        yield 'int aus null' => ['mandatory', null, 0];
        yield 'String aus int' => ['title', 42, '42'];
        yield 'String aus true' => ['title', true, '1'];
        yield 'String aus null (NOT NULL)' => ['title', null, ''];
        yield 'Text aus null (nullable)' => ['settings', null, null];
        yield 'Text bleibt mehrzeilig' => ['settings', "a\nb", "a\nb"];
    }

    #[Test]
    #[DataProvider('normalizeProvider')]
    public function normalizeCastsToColumnType(string $column, mixed $value, mixed $expected): void
    {
        self::assertSame($expected, ColumnsFactory::create()->normalize(Records::FIELD, $column, $value));
    }

    #[Test]
    public function normalizeRejectsLists(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Listen sind als Wert nicht erlaubt');
        ColumnsFactory::create()->normalize(Records::FIELD, 'settings', ['a', 'b']);
    }

    #[Test]
    public function normalizeKeepsUnknownColumnsAsString(): void
    {
        self::assertSame('7', ColumnsFactory::create()->normalize(Records::FIELD, 'sorting', 7));
    }

    #[Test]
    public function isDefaultTreatsEmptyStringAndNullAlike(): void
    {
        $columns = ColumnsFactory::create();
        self::assertTrue($columns->isDefault(Records::FIELD, 'settings', ''));
        self::assertTrue($columns->isDefault(Records::FIELD, 'settings', null));
        self::assertTrue($columns->isDefault(Records::FIELD, 'title', null));
    }

    #[Test]
    public function isDefaultDetectsChangedValues(): void
    {
        $columns = ColumnsFactory::create();
        self::assertTrue($columns->isDefault(Records::FIELD, 'mandatory', '0'));
        self::assertFalse($columns->isDefault(Records::FIELD, 'mandatory', 1));
        self::assertFalse($columns->isDefault(Records::FIELD, 'title', '0'));
        self::assertFalse($columns->isDefault(Records::FIELD, 'settings', 'x'));
    }

    #[Test]
    public function hasOnlyKnowsDefinedColumns(): void
    {
        $columns = ColumnsFactory::create();
        self::assertTrue($columns->has(Records::FIELD, 'type'));
        self::assertFalse($columns->has(Records::FIELD, 'marker'));
        self::assertFalse($columns->has(Records::FORM, 'type'));
    }
}
