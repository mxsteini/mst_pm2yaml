<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Service;

use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Welche Spalten in die YAML-Datei gehören: alle TCA-Spalten mit Datenbankspalte, ohne System-, Relations- und
 * Schlüsselspalten. Spalten aus anderen Extensions (z. B. powermail_cond) sind damit automatisch dabei.
 */
final class Columns
{
    private const SYSTEM = [
        'uid', 'pid', 'tstamp', 'crdate', 'deleted', 'sorting', 'sys_language_uid', 'l10n_parent', 'l10n_state',
        'l10n_diffsource', 'l10n_source', 't3_origuid', 't3ver_oid', 't3ver_wsid', 't3ver_state', 't3ver_stage',
        't3ver_label', 'is_dummy_record',
    ];

    private const RELATIONS = [
        Records::FORM => ['pages', 'note', Records::FILE_COLUMN, Records::SYNCED_COLUMN],
        Records::PAGE => ['form', 'fields', Records::IDENTIFIER_COLUMN],
        Records::FIELD => ['page', 'marker'],
    ];

    private const SKIPPED_TCA_TYPES = ['inline', 'user', 'none', 'passthrough'];

    /** @var array<string, array<string, array{default: mixed, int: bool, nullable: bool}>> */
    private array $definitions = [];

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<string, array{default: mixed, int: bool, nullable: bool}>
     */
    public function definitions(string $table): array
    {
        if (isset($this->definitions[$table])) {
            return $this->definitions[$table];
        }
        $schema = $this->connectionPool->getConnectionForTable($table)->createSchemaManager()->introspectTable($table);
        $dbColumns = [];
        foreach ($schema->getColumns() as $column) {
            $dbColumns[$column->getName()] = $column;
        }
        $excluded = array_merge(self::SYSTEM, self::RELATIONS[$table] ?? []);
        $definitions = [];
        foreach ($GLOBALS['TCA'][$table]['columns'] ?? [] as $name => $config) {
            if (in_array($name, $excluded, true) || !isset($dbColumns[$name])
                || in_array($config['config']['type'] ?? '', self::SKIPPED_TCA_TYPES, true)
            ) {
                continue;
            }
            $column = $dbColumns[$name];
            $type = $column->getType();
            $isInt = $type instanceof IntegerType || $type instanceof SmallIntType
                || $type instanceof BigIntType || $type instanceof BooleanType;
            $definition = ['default' => null, 'int' => $isInt, 'nullable' => !$column->getNotnull()];
            $definition['default'] = $this->cast($definition, $column->getDefault());
            $definitions[$name] = $definition;
        }
        return $this->definitions[$table] = $definitions;
    }

    public function has(string $table, string $column): bool
    {
        return isset($this->definitions($table)[$column]);
    }

    public function normalize(string $table, string $column, mixed $value): mixed
    {
        $definition = $this->definitions($table)[$column] ?? null;
        if ($definition === null) {
            return is_scalar($value) ? (string)$value : $value;
        }
        return $this->cast($definition, $value);
    }

    public function defaultValue(string $table, string $column): mixed
    {
        return $this->definitions($table)[$column]['default'];
    }

    /**
     * Standardwert (gehört nicht in die Datei). '' und NULL gelten als gleich.
     */
    public function isDefault(string $table, string $column, mixed $value): bool
    {
        $value = $this->normalize($table, $column, $value);
        $default = $this->defaultValue($table, $column);
        if (($value === '' || $value === null) && ($default === '' || $default === null)) {
            return true;
        }
        return $value === $default;
    }

    /**
     * @param array{default: mixed, int: bool, nullable: bool} $definition
     */
    private function cast(array $definition, mixed $value): mixed
    {
        if (is_array($value)) {
            throw new \InvalidArgumentException('Listen sind als Wert nicht erlaubt, mehrzeilige Werte bitte als Text (|) angeben', 1795250101);
        }
        if ($value === null) {
            if ($definition['nullable']) {
                return null;
            }
            return $definition['int'] ? 0 : '';
        }
        if ($definition['int']) {
            return (int)$value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string)$value;
    }
}
