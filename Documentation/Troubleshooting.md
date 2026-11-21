# Troubleshooting

## A change in the file does not arrive

- Is the form **linked** to the file? The field *YAML-Datei* must contain the file, not "nur Datenbank"
  (database only).
- The synchronization on display only runs if the **modification time** of the file changed. If the file was
  changed twice within the same second, run `vendor/bin/typo3 pm2yaml:sync --form=<uid>`.
- The file is **invalid**. Errors are logged (component `MSTITB.MstPm2yaml`, level `error`), and Powermail keeps
  working with the state of the database. An invalid file is retried on every request until it is fixed.

```bash
vendor/bin/typo3 pm2yaml:sync --form=12 --dry-run
```

## Typical error messages

The messages are in German. They are quoted verbatim with their meaning.

| Message | Cause |
|---|---|
| `Ungültiger Marker "…" (erlaubt: a-z, 0-9 und _)` | Invalid marker: upper case letters, hyphen or spaces |
| `Ungültiger Seiten-Bezeichner "…"` | Page identifier contains characters other than `A–Z a–z 0–9 _ -` |
| `Marker "…" kommt mehrfach vor` | A marker may occur only once per form, across pages as well |
| `Feld "…" hat keinen type` | The required `type` is missing |
| `unbekannte Eigenschaft "…"` | Typo, or the column does not exist in the Powermail tables |
| `Zuordnung (Schlüssel: Wert) erwartet` | A property has a list instead of a value |
| `Listen sind als Wert nicht erlaubt, mehrzeilige Werte bitte als Text (\|) angeben` | Write multi-line values with `\|` |
| `unbekannte Seite "…"` / `unbekanntes Feld "…"` | A translation refers to something missing in the default language |
| `Sprache "…" in localizations muss eine Sprach-ID > 0 sein` | Language id 0 is the default language |
| `Formular … ist eine Übersetzung` | The form of the default language has to be linked |

## The form is locked in the backend

The form is linked to a **versioned** file and the environment is not `Development` (or the extension is located
below `vendor/`). Changes belong into the file. To remove the link, set *YAML-Datei* to "nur Datenbank".

## The export button is missing

The button only appears for forms **without** a link.

## The file is accessible via HTTP

The extension only creates the `.htaccess` for Apache. With Nginx, block the folder (default
`public/fileadmin/_Powermail/`) with a `location` rule, or store the files outside of `public/`.
