# Fehlersuche

## Eine Änderung in der Datei kommt nicht an

- Ist das Formular mit der Datei **verknüpft**? Im Formular muss unter *YAML-Datei* die Datei stehen, nicht
  „nur Datenbank“.
- Der Abgleich beim Anzeigen läuft nur, wenn sich die **Änderungszeit** der Datei geändert hat. Wurde die Datei
  zweimal in derselben Sekunde geändert, hilft `vendor/bin/typo3 pm2yaml:sync --form=<uid>`.
- Die Datei ist **fehlerhaft**. Fehler werden geloggt (Komponente `MSTITB.MstPm2yaml`, Level `error`),
  Powermail arbeitet dann mit dem Stand der Datenbank. Eine fehlerhafte Datei wird bei jedem Aufruf erneut
  versucht, bis sie korrigiert ist.

```bash
vendor/bin/typo3 pm2yaml:sync --form=12 --dry-run
```

## Typische Fehlermeldungen

| Meldung | Ursache |
|---|---|
| `Ungültiger Marker "…" (erlaubt: a-z, 0-9 und _)` | Marker mit Großbuchstaben, Bindestrich oder Leerzeichen |
| `Ungültiger Seiten-Bezeichner "…"` | Bezeichner enthält andere Zeichen als `A–Z a–z 0–9 _ -` |
| `Marker "…" kommt mehrfach vor` | Ein Marker ist im Formular nur einmal erlaubt, auch über Seiten hinweg |
| `Feld "…" hat keinen type` | Pflichtangabe `type` fehlt |
| `unbekannte Eigenschaft "…"` | Tippfehler oder Spalte existiert nicht in den Powermail-Tabellen |
| `Zuordnung (Schlüssel: Wert) erwartet` | Eine Eigenschaft hat eine Liste statt eines Werts |
| `Listen sind als Wert nicht erlaubt, mehrzeilige Werte bitte als Text (\|) angeben` | Mehrzeilige Werte mit `\|` schreiben |
| `unbekannte Seite "…"` / `unbekanntes Feld "…"` | Übersetzung verweist auf etwas, das in der Standardsprache fehlt |
| `Sprache "…" in localizations muss eine Sprach-ID > 0 sein` | Sprach-ID 0 ist die Standardsprache |
| `Formular … ist eine Übersetzung` | Es muss das Formular der Standardsprache verknüpft werden |

## Das Formular ist im Backend gesperrt

Das Formular ist mit einer **versionierten** Datei verknüpft und die Umgebung ist nicht `Development`
(oder die Extension liegt unter `vendor/`). Änderungen gehören in die Datei. Zum Lösen der Verknüpfung
*YAML-Datei* auf „nur Datenbank“ stellen.

## Der Export-Button fehlt

Der Button erscheint nur bei Formularen **ohne** Verknüpfung.

## Die Datei ist per HTTP abrufbar

Die Extension legt die `.htaccess` nur für Apache an. Bei Nginx den Ordner (Standard
`public/fileadmin/_Powermail/`) mit einer `location`-Regel sperren oder die Dateien außerhalb von `public/`
ablegen.
