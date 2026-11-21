<?php

declare(strict_types=1);

namespace MSTITB\MstPm2yaml\Service;

/**
 * Datenbank → Array für die YAML-Datei. Seiten werden über ihren Bezeichner, Felder über ihren Marker
 * identifiziert, Standardwerte weggelassen. Übersetzungen enthalten nur die Abweichungen.
 *
 * Fehlende Bezeichner und Marker werden aus dem Titel erzeugt und in der Datenbank gespeichert.
 */
final class FormExporter
{
    private const FIELD_FIRST = ['type', 'title'];

    public function __construct(
        private readonly Records $records,
        private readonly Columns $columns,
    ) {}

    public function build(int $formUid): array
    {
        $form = $this->records->byUid(Records::FORM, $formUid)
            ?? throw new \RuntimeException('Formular ' . $formUid . ' nicht gefunden', 1795250201);

        $data = $this->values(Records::FORM, $form);
        $data['pages'] = [];
        $pageIdentifiers = [];
        $fieldMarkers = [];
        $usedIdentifiers = [];
        $usedMarkers = [];

        foreach ($this->records->live(Records::PAGE, ['form' => $formUid, 'sys_language_uid' => 0]) as $page) {
            $identifier = $this->unique(
                (string)$page[Records::IDENTIFIER_COLUMN] ?: FileLocator::slug((string)$page['title'], '-') ?: 'seite',
                $usedIdentifiers,
                '-'
            );
            if ($identifier !== $page[Records::IDENTIFIER_COLUMN]) {
                $this->records->update(Records::PAGE, (int)$page['uid'], [Records::IDENTIFIER_COLUMN => $identifier]);
            }
            $pageIdentifiers[(int)$page['uid']] = $identifier;

            $pageData = $this->values(Records::PAGE, $page);
            $pageData['fields'] = [];
            foreach ($this->records->live(Records::FIELD, ['page' => (int)$page['uid'], 'sys_language_uid' => 0]) as $field) {
                $marker = $this->unique(
                    (string)$field['marker'] ?: FileLocator::slug((string)$field['title'], '_') ?: 'feld',
                    $usedMarkers,
                    '_'
                );
                if ($marker !== $field['marker']) {
                    $this->records->update(Records::FIELD, (int)$field['uid'], ['marker' => $marker]);
                }
                $fieldMarkers[(int)$field['uid']] = $marker;
                $pageData['fields'][$marker] = $this->values(Records::FIELD, $field);
            }
            $data['pages'][$identifier] = $pageData;
        }

        $localizations = $this->localizations($form, $pageIdentifiers, $fieldMarkers);
        if ($localizations !== []) {
            $data['localizations'] = $localizations;
        }
        return $data;
    }

    /**
     * @param array<int, string> $pageIdentifiers
     * @param array<int, string> $fieldMarkers
     */
    private function localizations(array $form, array $pageIdentifiers, array $fieldMarkers): array
    {
        $result = [];
        foreach ($this->records->translations(Records::FORM, [(int)$form['uid']]) as $translation) {
            $language = (int)$translation['sys_language_uid'];
            if (!isset($result[$language])) {
                $result[$language] = $this->diff(Records::FORM, $translation, $form);
            }
        }
        foreach ([Records::PAGE => $pageIdentifiers, Records::FIELD => $fieldMarkers] as $table => $keys) {
            $parents = [];
            foreach (array_keys($keys) as $uid) {
                $parents[$uid] = $this->records->byUid($table, $uid);
            }
            $section = $table === Records::PAGE ? 'pages' : 'fields';
            foreach ($this->records->translations($table, array_keys($keys)) as $translation) {
                $language = (int)$translation['sys_language_uid'];
                $parent = $parents[(int)$translation['l10n_parent']] ?? null;
                if ($parent === null || !isset($result[$language])) {
                    continue;
                }
                $key = $keys[(int)$parent['uid']];
                $diff = $this->diff($table, $translation, $parent);
                if ($diff !== [] && !isset($result[$language][$section][$key])) {
                    $result[$language][$section][$key] = $diff;
                }
            }
        }
        ksort($result);
        return $result;
    }

    private function values(string $table, array $row): array
    {
        $values = [];
        foreach (array_keys($this->columns->definitions($table)) as $column) {
            $value = $this->lineBreaks($this->columns->normalize($table, $column, $row[$column] ?? null));
            if ($table === Records::FIELD && in_array($column, self::FIELD_FIRST, true)) {
                $values[$column] = $value;
            } elseif (!$this->columns->isDefault($table, $column, $value)) {
                $values[$column] = $value;
            }
        }
        if ($table === Records::FIELD) {
            $values = array_merge(array_intersect_key(array_flip(self::FIELD_FIRST), $values), $values);
        }
        return $values;
    }

    private function diff(string $table, array $translation, array $parent): array
    {
        $values = [];
        foreach (array_keys($this->columns->definitions($table)) as $column) {
            $value = $this->lineBreaks($this->columns->normalize($table, $column, $translation[$column] ?? null));
            if ($value !== $this->lineBreaks($this->columns->normalize($table, $column, $parent[$column] ?? null))) {
                $values[$column] = $value;
            }
        }
        return $values;
    }

    /**
     * Backend-Textfelder speichern CRLF, in der Datei steht LF (lesbare Blöcke mit |).
     */
    private function lineBreaks(mixed $value): mixed
    {
        return is_string($value) ? str_replace(["\r\n", "\r"], "\n", $value) : $value;
    }

    /**
     * @param array<string, true> $used
     */
    private function unique(string $key, array &$used, string $separator): string
    {
        $candidate = $key;
        for ($i = 2; isset($used[$candidate]); $i++) {
            $candidate = $key . $separator . $i;
        }
        $used[$candidate] = true;
        return $candidate;
    }
}
