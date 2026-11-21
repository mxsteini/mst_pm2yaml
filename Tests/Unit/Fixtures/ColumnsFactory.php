<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Tests\Unit\Fixtures;

use MSTITB\MstPm2yaml\Service\Columns;
use MSTITB\MstPm2yaml\Service\Records;

/**
 * Columns mit festen Spaltendefinitionen statt Schema-Abfrage.
 */
final class ColumnsFactory
{
    private const STRING = ['default' => '', 'int' => false, 'nullable' => false];
    private const INT = ['default' => 0, 'int' => true, 'nullable' => false];
    private const TEXT = ['default' => null, 'int' => false, 'nullable' => true];

    public static function create(): Columns
    {
        $columns = (new \ReflectionClass(Columns::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Columns::class, 'definitions'))->setValue($columns, [
            Records::FORM => ['title' => self::STRING, 'css' => self::STRING, 'hidden' => self::INT],
            Records::PAGE => ['title' => self::STRING, 'css' => self::STRING, 'hidden' => self::INT],
            Records::FIELD => [
                'type' => self::STRING, 'title' => self::STRING, 'settings' => self::TEXT,
                'mandatory' => self::INT, 'css' => self::STRING, 'hidden' => self::INT,
            ],
        ]);
        return $columns;
    }
}
