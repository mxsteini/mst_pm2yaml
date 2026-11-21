<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Tests\Unit\Fixtures;

use MSTITB\MstPm2yaml\Service\Records;
use TYPO3\CMS\Core\Database\Connection;

/**
 * Powermail-Tabellen im Speicher, gleiche Semantik wie Records (deleted, t3ver_wsid, Sortierung).
 */
final class InMemoryRecords extends Records
{
    /** @var array<string, array<int, array<string, mixed>>> */
    public array $rows = [self::FORM => [], self::PAGE => [], self::FIELD => []];
    private int $nextUid = 1000;

    public function __construct() {}

    public function connection(): Connection
    {
        throw new \LogicException('Keine Datenbank im Unit-Test', 1795260001);
    }

    public function add(string $table, array $row): int
    {
        $uid = (int)($row['uid'] ?? $this->nextUid++);
        $this->rows[$table][$uid] = $row + [
            'uid' => $uid, 'pid' => 87, 'deleted' => 0, 't3ver_wsid' => 0, 't3ver_oid' => 0,
            'sys_language_uid' => 0, 'l10n_parent' => 0, 'sorting' => 0, 'tstamp' => 0, 'crdate' => 0,
        ] + $this->tableDefaults($table);
        return $uid;
    }

    public function byUid(string $table, int $uid, bool $withDeleted = false): ?array
    {
        $row = $this->rows[$table][$uid] ?? null;
        if ($row === null || (!$withDeleted && (int)$row['deleted'] === 1)) {
            return null;
        }
        return $row;
    }

    public function live(string $table, array $where, string $orderBy = 'sorting'): array
    {
        $result = array_filter($this->rows[$table], static function (array $row) use ($where): bool {
            if ((int)$row['deleted'] !== 0 || (int)$row['t3ver_wsid'] !== 0) {
                return false;
            }
            foreach ($where as $column => $value) {
                if (is_array($value) ? !in_array((int)$row[$column], $value, true) : (int)$row[$column] !== $value) {
                    return false;
                }
            }
            return true;
        });
        usort($result, static fn(array $a, array $b): int => $table === self::FORM
            ? $a['uid'] <=> $b['uid']
            : [$a['sorting'], $a['uid']] <=> [$b['sorting'], $b['uid']]);
        return array_values($result);
    }

    public function translations(string $table, array $parents): array
    {
        $result = array_filter($this->rows[$table], static fn(array $row): bool => (int)$row['deleted'] === 0
            && (int)$row['t3ver_wsid'] === 0 && (int)$row['sys_language_uid'] > 0
            && in_array((int)$row['l10n_parent'], $parents, true));
        usort($result, static fn(array $a, array $b): int => [$a['sys_language_uid'], $a['uid']] <=> [$b['sys_language_uid'], $b['uid']]);
        return array_values($result);
    }

    public function insert(string $table, array $values): int
    {
        return $this->add($table, $values);
    }

    public function update(string $table, int $uid, array $values): void
    {
        $this->rows[$table][$uid] = array_merge($this->rows[$table][$uid], $values);
    }

    /**
     * Nicht gelöschte Live-Zeilen einer Tabelle, nach Spalte gefiltert.
     */
    public function find(string $table, array $where): array
    {
        return array_values(array_filter($this->rows[$table], static function (array $row) use ($where): bool {
            foreach ($where as $column => $value) {
                if ($row[$column] != $value) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function tableDefaults(string $table): array
    {
        return match ($table) {
            self::FORM => ['title' => '', 'css' => '', 'hidden' => 0, 'pages' => 0, self::FILE_COLUMN => '', self::SYNCED_COLUMN => 0],
            self::PAGE => ['form' => 0, 'title' => '', 'css' => '', 'hidden' => 0, 'fields' => 0, self::IDENTIFIER_COLUMN => ''],
            self::FIELD => ['page' => 0, 'marker' => '', 'type' => '', 'title' => '', 'settings' => null, 'mandatory' => 0, 'css' => '', 'hidden' => 0],
        };
    }
}
