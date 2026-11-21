<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Service;

/**
 * Array aus der YAML-Datei → Datenbank, für ein bestehendes Formular (Standardsprache).
 *
 * Seiten werden über den Bezeichner zugeordnet (Fallback: Seite ohne Bezeichner, deren Titel passt), Felder über
 * den Marker, formularweit. Zugeordnete Datensätze behalten ihre uid, damit gespeicherte Mails weiter auf ihre
 * Felder zeigen. Was nicht mehr in der Datei steht, wird gelöscht (deleted = 1), ebenso nicht mehr enthaltene
 * Sprachen. Workspace-Entwürfe bleiben unberührt.
 */
final class FormImporter
{
    private bool $changed = false;
    private int $pid = 0;

    public function __construct(
        private readonly Records $records,
        private readonly Columns $columns,
    ) {}

    /**
     * @return bool ob sich etwas geändert hat
     */
    public function import(int $formUid, array $data): bool
    {
        $this->changed = false;
        $form = $this->records->byUid(Records::FORM, $formUid)
            ?? throw new \RuntimeException('Formular ' . $formUid . ' nicht gefunden', 1795250301);
        if ((int)$form['sys_language_uid'] !== 0) {
            throw new \RuntimeException('Formular ' . $formUid . ' ist eine Übersetzung', 1795250302);
        }
        $this->pid = (int)$form['pid'];
        $pagesData = $this->map($data['pages'] ?? [], 'pages');

        $this->write(Records::FORM, $formUid, $this->rowValues(Records::FORM, $data, ['pages', 'localizations'], 'Formular'));

        $existingPages = $this->records->live(Records::PAGE, ['form' => $formUid, 'sys_language_uid' => 0]);
        $existingFields = [];
        $duplicateFields = [];
        foreach ($this->records->live(Records::FIELD, ['page' => array_map('intval', array_column($existingPages, 'uid')), 'sys_language_uid' => 0]) as $field) {
            if (isset($existingFields[$field['marker']]) || $field['marker'] === '') {
                $duplicateFields[] = (int)$field['uid'];
            } else {
                $existingFields[$field['marker']] = $field;
            }
        }

        $pageUids = [];
        $fieldUids = [];
        $fieldPage = [];
        $usedPages = [];
        $pageSorting = 0;
        foreach ($pagesData as $identifier => $pageData) {
            $identifier = (string)$identifier;
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $identifier)) {
                throw new \RuntimeException('Ungültiger Seiten-Bezeichner "' . $identifier . '" (erlaubt: A-Z, a-z, 0-9, _ und -)', 1795250303);
            }
            $pageData = $this->map($pageData, 'Seite ' . $identifier);
            $match = $this->matchPage($existingPages, $usedPages, $identifier);
            $fieldsData = $this->map($pageData['fields'] ?? [], 'Seite ' . $identifier . ', fields');
            $pageUid = $this->write(
                Records::PAGE,
                $match,
                $this->rowValues(Records::PAGE, $pageData, ['fields'], 'Seite ' . $identifier) + [
                    'form' => $formUid,
                    Records::IDENTIFIER_COLUMN => $identifier,
                    'sorting' => ++$pageSorting,
                    'fields' => count($fieldsData),
                    'sys_language_uid' => 0,
                    'l10n_parent' => 0,
                ]
            );
            $usedPages[$pageUid] = true;
            $pageUids[$identifier] = $pageUid;

            $fieldSorting = 0;
            foreach ($fieldsData as $marker => $fieldData) {
                $marker = (string)$marker;
                if (!preg_match('/^[a-z0-9_]+$/', $marker)) {
                    throw new \RuntimeException('Ungültiger Marker "' . $marker . '" (erlaubt: a-z, 0-9 und _)', 1795250304);
                }
                if (isset($fieldUids[$marker])) {
                    throw new \RuntimeException('Marker "' . $marker . '" kommt mehrfach vor', 1795250305);
                }
                $fieldData = $this->map($fieldData, 'Feld ' . $marker);
                if (($fieldData['type'] ?? '') === '') {
                    throw new \RuntimeException('Feld "' . $marker . '" hat keinen type', 1795250306);
                }
                $fieldUids[$marker] = $this->write(
                    Records::FIELD,
                    isset($existingFields[$marker]) ? (int)$existingFields[$marker]['uid'] : null,
                    $this->rowValues(Records::FIELD, $fieldData, [], 'Feld ' . $marker) + [
                        'page' => $pageUid,
                        'marker' => $marker,
                        'sorting' => ++$fieldSorting,
                        'sys_language_uid' => 0,
                        'l10n_parent' => 0,
                    ]
                );
                $fieldPage[$marker] = $identifier;
            }
        }
        $this->write(Records::FORM, $formUid, ['pages' => count($pageUids)]);

        foreach ($existingPages as $page) {
            if (!isset($usedPages[(int)$page['uid']])) {
                $this->delete(Records::PAGE, (int)$page['uid']);
            }
        }
        foreach ($existingFields as $marker => $field) {
            if (!isset($fieldUids[$marker])) {
                $this->delete(Records::FIELD, (int)$field['uid']);
            }
        }
        foreach ($duplicateFields as $uid) {
            $this->delete(Records::FIELD, $uid);
        }

        $this->importLocalizations($formUid, $this->map($data['localizations'] ?? [], 'localizations'), $pageUids, $fieldUids, $fieldPage);
        return $this->changed;
    }

    /**
     * @param array<string, int> $pageUids
     * @param array<string, int> $fieldUids
     * @param array<string, string> $fieldPage
     */
    private function importLocalizations(int $formUid, array $localizations, array $pageUids, array $fieldUids, array $fieldPage): void
    {
        $keep = [Records::FORM => [], Records::PAGE => [], Records::FIELD => []];
        $form = $this->records->byUid(Records::FORM, $formUid);
        $fieldCount = array_count_values($fieldPage);

        foreach ($localizations as $language => $localization) {
            if (!is_int($language) || $language <= 0) {
                throw new \RuntimeException('Sprache "' . $language . '" in localizations muss eine Sprach-ID > 0 sein', 1795250307);
            }
            $label = 'localizations ' . $language;
            $localization = $this->map($localization, $label);
            $pagesData = $this->map($localization['pages'] ?? [], $label . ', pages');
            $fieldsData = $this->map($localization['fields'] ?? [], $label . ', fields');
            foreach (array_diff_key($pagesData, $pageUids) as $identifier => $_) {
                throw new \RuntimeException($label . ': unbekannte Seite "' . $identifier . '"', 1795250308);
            }
            foreach (array_diff_key($fieldsData, $fieldUids) as $marker => $_) {
                throw new \RuntimeException($label . ': unbekanntes Feld "' . $marker . '"', 1795250309);
            }

            $translatedFormUid = $this->write(
                Records::FORM,
                $this->existingTranslation(Records::FORM, $formUid, $language),
                $this->translatedValues(Records::FORM, $form, $localization, ['pages', 'fields'], $label) + [
                    'pages' => count($pageUids),
                    'sys_language_uid' => $language,
                    'l10n_parent' => $formUid,
                ]
            );
            $keep[Records::FORM][] = $translatedFormUid;

            $translatedPages = [];
            foreach ($pageUids as $identifier => $pageUid) {
                $parent = $this->records->byUid(Records::PAGE, $pageUid);
                $translatedPages[$identifier] = $this->write(
                    Records::PAGE,
                    $this->existingTranslation(Records::PAGE, $pageUid, $language),
                    $this->translatedValues(Records::PAGE, $parent, $this->map($pagesData[$identifier] ?? [], $label . ', Seite ' . $identifier), [], $label . ', Seite ' . $identifier) + [
                        'form' => $translatedFormUid,
                        'sorting' => (int)$parent['sorting'],
                        'fields' => $fieldCount[$identifier] ?? 0,
                        'sys_language_uid' => $language,
                        'l10n_parent' => $pageUid,
                    ]
                );
                $keep[Records::PAGE][] = $translatedPages[$identifier];
            }
            foreach ($fieldUids as $marker => $fieldUid) {
                $parent = $this->records->byUid(Records::FIELD, $fieldUid);
                $keep[Records::FIELD][] = $this->write(
                    Records::FIELD,
                    $this->existingTranslation(Records::FIELD, $fieldUid, $language),
                    $this->translatedValues(Records::FIELD, $parent, $this->map($fieldsData[$marker] ?? [], $label . ', Feld ' . $marker), [], $label . ', Feld ' . $marker) + [
                        'page' => $translatedPages[$fieldPage[$marker]],
                        'marker' => $marker,
                        'sorting' => (int)$parent['sorting'],
                        'sys_language_uid' => $language,
                        'l10n_parent' => $fieldUid,
                    ]
                );
            }
        }

        // Übersetzungen, die nicht mehr in der Datei stehen (andere Sprachen, Dubletten)
        $parents = [Records::FORM => [$formUid], Records::PAGE => array_values($pageUids), Records::FIELD => array_values($fieldUids)];
        foreach ($parents as $table => $uids) {
            foreach ($this->records->translations($table, $uids) as $translation) {
                if (!in_array((int)$translation['uid'], $keep[$table], true)) {
                    $this->records->softDelete($table, (int)$translation['uid']);
                    $this->changed = true;
                }
            }
        }
    }

    private function matchPage(array $existingPages, array $usedPages, string $identifier): ?int
    {
        foreach ($existingPages as $page) {
            if (!isset($usedPages[(int)$page['uid']]) && $page[Records::IDENTIFIER_COLUMN] === $identifier) {
                return (int)$page['uid'];
            }
        }
        foreach ($existingPages as $page) {
            if (!isset($usedPages[(int)$page['uid']]) && $page[Records::IDENTIFIER_COLUMN] === ''
                && FileLocator::slug((string)$page['title'], '-') === $identifier
            ) {
                return (int)$page['uid'];
            }
        }
        return null;
    }

    private function existingTranslation(string $table, int $parentUid, int $language): ?int
    {
        foreach ($this->records->translations($table, [$parentUid]) as $translation) {
            if ((int)$translation['sys_language_uid'] === $language) {
                return (int)$translation['uid'];
            }
        }
        return null;
    }

    /**
     * Alle Spalten: Wert aus der Datei, sonst Standardwert.
     *
     * @param list<string> $nested Schlüssel, die keine Spalten sind
     */
    private function rowValues(string $table, array $data, array $nested, string $label): array
    {
        $this->assertKnownKeys($table, $data, $nested, $label);
        $values = [];
        foreach (array_keys($this->columns->definitions($table)) as $column) {
            $values[$column] = array_key_exists($column, $data)
                ? $this->normalize($table, $column, $data[$column], $label)
                : $this->columns->defaultValue($table, $column);
        }
        return $values;
    }

    /**
     * Übersetzung: Werte der Standardsprache, überschrieben mit den Abweichungen aus der Datei.
     */
    private function translatedValues(string $table, array $parent, array $diff, array $nested, string $label): array
    {
        $this->assertKnownKeys($table, $diff, $nested, $label);
        $values = [];
        foreach (array_keys($this->columns->definitions($table)) as $column) {
            $values[$column] = array_key_exists($column, $diff)
                ? $this->normalize($table, $column, $diff[$column], $label)
                : $this->columns->normalize($table, $column, $parent[$column] ?? null);
        }
        return $values;
    }

    private function assertKnownKeys(string $table, array $data, array $nested, string $label): void
    {
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $nested, true) && !$this->columns->has($table, (string)$key)) {
                throw new \RuntimeException($label . ': unbekannte Eigenschaft "' . $key . '"', 1795250310);
            }
        }
    }

    private function normalize(string $table, string $column, mixed $value, string $label): mixed
    {
        try {
            return $this->columns->normalize($table, $column, $value);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException($label . ', ' . $column . ': ' . $e->getMessage(), 1795250311, $e);
        }
    }

    private function map(mixed $value, string $label): array
    {
        if ($value === null) {
            return [];
        }
        // Listen (- …) sind nicht erlaubt: Seiten, Felder und Eigenschaften brauchen Schlüssel
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \RuntimeException($label . ': Zuordnung (Schlüssel: Wert) erwartet', 1795250312);
        }
        return $value;
    }

    /**
     * Legt an oder aktualisiert nur, was sich unterscheidet.
     */
    private function write(string $table, ?int $uid, array $values): int
    {
        $now = time();
        if ($uid === null) {
            $this->changed = true;
            return $this->records->insert($table, $values + ['pid' => $this->pid, 'crdate' => $now, 'tstamp' => $now]);
        }
        $current = $this->records->byUid($table, $uid, true) ?? [];
        $changes = [];
        foreach ($values as $column => $value) {
            $old = $this->columns->has($table, $column)
                ? $this->columns->normalize($table, $column, $current[$column] ?? null)
                : (string)($current[$column] ?? '');
            $new = $this->columns->has($table, $column) ? $value : (string)$value;
            if ($old !== $new) {
                $changes[$column] = $value;
            }
        }
        if ((int)($current['pid'] ?? $this->pid) !== $this->pid) {
            $changes['pid'] = $this->pid;
        }
        if ($changes !== []) {
            $this->records->update($table, $uid, $changes + ['tstamp' => $now]);
            $this->changed = true;
        }
        return $uid;
    }

    private function delete(string $table, int $uid): void
    {
        $this->records->softDelete($table, $uid);
        foreach ($this->records->translations($table, [$uid]) as $translation) {
            $this->records->softDelete($table, (int)$translation['uid']);
        }
        $this->changed = true;
    }
}
