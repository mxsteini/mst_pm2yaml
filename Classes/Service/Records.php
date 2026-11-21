<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Direkter Zugriff auf die Powermail-Tabellen (Live-Datensätze, ohne Hooks).
 * Nicht final: Unit-Tests ersetzen die Klasse durch eine In-Memory-Variante.
 */
class Records
{
    public const FORM = 'tx_powermail_domain_model_form';
    public const PAGE = 'tx_powermail_domain_model_page';
    public const FIELD = 'tx_powermail_domain_model_field';
    public const TABLES = [self::FORM, self::PAGE, self::FIELD];

    public const FILE_COLUMN = 'tx_mstpm2yaml_file';
    public const IDENTIFIER_COLUMN = 'tx_mstpm2yaml_identifier';
    public const SYNCED_COLUMN = 'tx_mstpm2yaml_synced';

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function connection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::FORM);
    }

    public function byUid(string $table, int $uid, bool $withDeleted = false): ?array
    {
        $qb = $this->queryBuilder($table);
        $qb->select('*')->from($table)->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, Connection::PARAM_INT)));
        if (!$withDeleted) {
            $qb->andWhere($qb->expr()->eq('deleted', 0));
        }
        $row = $qb->executeQuery()->fetchAssociative();
        return $row === false ? null : $row;
    }

    /**
     * Nicht gelöschte Live-Datensätze (kein Workspace-Entwurf), sortiert.
     *
     * @param array<string, int|int[]> $where Spalte => Wert oder Liste von Werten
     * @return list<array<string, mixed>>
     */
    public function live(string $table, array $where, string $orderBy = 'sorting'): array
    {
        $qb = $this->queryBuilder($table);
        $qb->select('*')->from($table)
            ->where($qb->expr()->eq('deleted', 0), $qb->expr()->eq('t3ver_wsid', 0));
        foreach ($where as $column => $value) {
            if (is_array($value)) {
                if ($value === []) {
                    return [];
                }
                $qb->andWhere($qb->expr()->in($column, $qb->createNamedParameter($value, Connection::PARAM_INT_ARRAY)));
            } else {
                $qb->andWhere($qb->expr()->eq($column, $qb->createNamedParameter($value, Connection::PARAM_INT)));
            }
        }
        if ($orderBy !== '' && $table !== self::FORM) {
            $qb->orderBy($orderBy)->addOrderBy('uid');
        } else {
            $qb->orderBy('uid');
        }
        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Übersetzungen (sys_language_uid > 0) zu den angegebenen Eltern-Datensätzen.
     *
     * @param int[] $parents
     * @return list<array<string, mixed>>
     */
    public function translations(string $table, array $parents): array
    {
        if ($parents === []) {
            return [];
        }
        $qb = $this->queryBuilder($table);
        return $qb->select('*')->from($table)
            ->where(
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('t3ver_wsid', 0),
                $qb->expr()->gt('sys_language_uid', 0),
                $qb->expr()->in('l10n_parent', $qb->createNamedParameter($parents, Connection::PARAM_INT_ARRAY)),
            )
            ->orderBy('sys_language_uid')->addOrderBy('uid')
            ->executeQuery()->fetchAllAssociative();
    }

    public function insert(string $table, array $values): int
    {
        $connection = $this->connectionPool->getConnectionForTable($table);
        $connection->insert($table, $values);
        return (int)$connection->lastInsertId();
    }

    public function update(string $table, int $uid, array $values): void
    {
        $this->connectionPool->getConnectionForTable($table)->update($table, $values, ['uid' => $uid]);
    }

    public function softDelete(string $table, int $uid): void
    {
        $this->update($table, $uid, ['deleted' => 1, 'tstamp' => time()]);
    }

    /**
     * Uid des Formulars in der Standardsprache, zu dem ein Formular, eine Seite oder ein Feld gehört.
     * Berücksichtigt gelöschte Datensätze (nach dem Löschen) und Workspace-Versionen.
     */
    public function defaultFormUid(string $table, int $uid): ?int
    {
        $row = $this->byUid($table, $uid, true);
        if ($row === null) {
            return null;
        }
        if ((int)($row['t3ver_oid'] ?? 0) > 0) {
            $row = $this->byUid($table, (int)$row['t3ver_oid'], true) ?? $row;
        }
        if ($table === self::FIELD) {
            return (int)$row['page'] > 0 ? $this->defaultFormUid(self::PAGE, (int)$row['page']) : null;
        }
        if ($table === self::PAGE) {
            return (int)$row['form'] > 0 ? $this->defaultFormUid(self::FORM, (int)$row['form']) : null;
        }
        if ((int)$row['sys_language_uid'] > 0 && (int)$row['l10n_parent'] > 0) {
            return (int)$row['l10n_parent'];
        }
        return (int)$row['uid'];
    }

    private function queryBuilder(string $table): QueryBuilder
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll();
        return $qb;
    }
}
