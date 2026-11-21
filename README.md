# mst_pm2yaml – Powermail-Formulare als YAML-Datei

Ein Powermail-Formular (Formular, Seiten, Felder, Übersetzungen) wird mit einer YAML-Datei abgeglichen.
Powermail selbst bleibt unverändert: Es liest wie immer aus der Datenbank, die Extension hält Datei und
Datenbank synchron. Die Datei enthält keine uids.

## Datei auswählen

Am Formular gibt es das Feld **YAML-Datei**. Zur Auswahl stehen

| Quelle | Beispiel | Bearbeiten im Backend |
|---|---|---|
| versioniert | `EXT:mstitb_bootstrap/Configuration/Powermail/kontakt.yaml` | nur im Development-Kontext, sonst schreibgeschützt |
| Ordner der Site (nicht versioniert) | `fileadmin/_Powermail/kontakt.yaml` | immer |
| keine (nur Datenbank) | | wie bisher, plus Export-Button neben dem Feld |

- **Datei auswählen** übernimmt den Inhalt der Datei in die Datenbank (die Datei gewinnt).
- **Anzeigen des Plugins** (Frontend, Vorschau im Seitenmodul, Bearbeiten von Plugin, Formular, Seite oder Feld):
  Vorher wird die Datei übernommen, wenn sich ihre Änderungszeit seit dem letzten Abgleich geändert hat
  (`tx_mstpm2yaml_synced` am Formular, gesetzt bei jedem Import und Schreiben). Fehler beim Abgleich werden
  nur geloggt, Powermail arbeitet dann mit dem Stand der Datenbank. Eine fehlerhafte Datei wird bei jedem
  Aufruf erneut versucht, bis sie korrigiert ist.
- **Speichern** im Backend (Live-Workspace oder Veröffentlichen) schreibt Formular, Seiten und Felder in die
  Datei, wenn sie schreibbar ist. Entwürfe in Workspaces schreiben die Datei nicht.
- **Export-Button** (nur ohne Datei): schreibt das Formular in den Ordner der Site und verknüpft es.
- Schreibgeschützte Formulare: Ändern, Anlegen, Löschen, Verschieben und Übersetzen von Seiten und Feldern wird
  abgelehnt; nur die Dateiauswahl bleibt änderbar („nur Datenbank“ hebt die Verknüpfung auf).

## Ordner

1. Site-Set `mstitb/mst-pm2yaml`, Einstellung `mstPm2yaml.folder` der Site, in der das Formular liegt
2. Extension-Konfiguration `folder` (`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['mst_pm2yaml']['folder']`)
3. `fileadmin/_Powermail/`

Pfade relativ zum public-Verzeichnis. Liegt der Ordner unter public/, legt die Extension eine `.htaccess`
mit `Require all denied` an.

## Format

```yaml
title: 'Bewerbung für die Testphase'
pages:
  deine-angaben:              # Bezeichner der Seite (Feld „Bezeichner in der YAML-Datei“)
    title: 'Deine Angaben'
    fields:
      app:                    # Marker des Feldes
        type: radio
        title: 'Für welche App bewirbst du dich?'
        settings: |-
          Octavo Sheets
          Octavo Train
        mandatory: 1
localizations:
  1:                          # Sprach-ID der Site
    title: 'Application for the test phase'
    pages:
      deine-angaben:
        title: 'Your details'
    fields:
      app:
        title: 'Which app are you applying for?'
```

- Eigenschaften sind die Spalten der Powermail-Tabellen (auch aus anderen Extensions), Standardwerte fehlen.
- Seiten werden über den Bezeichner, Felder formularweit über den Marker zugeordnet. Zugeordnete Datensätze
  behalten ihre uid, gespeicherte Mails zeigen also weiter auf ihre Felder. Ein umbenannter Marker ist ein
  neues Feld.
- Was nicht mehr in der Datei steht, wird gelöscht (`deleted = 1`), auch Übersetzungen.
- Übersetzungen enthalten nur Abweichungen von der Standardsprache; jede Sprache in `localizations` bekommt
  alle Seiten und Felder.

## Kommandos

```bash
typo3 pm2yaml:export <form-uid> [--file=EXT:…/Configuration/Powermail/<name>.yaml]
typo3 pm2yaml:sync [--form=<uid>] [--dry-run] [--only-locked]
```

`deployment.sh` führt beim Publish `pm2yaml:sync --only-locked` aus: versionierte Dateien gehen zusammen mit
dem Code live. Nach einem Rollback bei Bedarf von Hand ausführen.

Ablauf für ein versioniertes Formular: in DDEV bearbeiten (Backend oder Datei), Datei committen, deployen.

## Tests

```bash
# Unit-Tests (ohne Datenbank): Spalten, Ordner, Dateipfade/Schreibschutz, Import und Export
ddev exec vendor/bin/phpunit -c packages/mst_pm2yaml/Tests/phpunit.xml

# E2E-Tests gegen die laufende DDEV-Instanz (nutzt den installierten Chrome)
cd packages/mst_pm2yaml/Tests/E2E && npm ci && npx playwright test
```

Die E2E-Tests legen eine eigene Seite `/e2e-pm2yaml` mit Formularen und Plugin an (uids ab 9001, Dateien
`fileadmin/_Powermail/e2e-*.yaml`) und entfernen alles danach wieder (`E2E_KEEP_FIXTURE=1` lässt es stehen).
Der Mailversand wird über Mailpit geprüft. Der Schreibschutz außerhalb von Development ist nur per Unit-Test
abgedeckt, weil DDEV im Development-Kontext läuft.

Hinweis: Die Änderungszeit einer Datei hat Sekundenauflösung. Wird eine Datei zweimal in derselben Sekunde
geändert, nachdem dazwischen schon abgeglichen wurde, bemerkt der Abgleich beim Anzeigen die zweite Änderung
nicht (`pm2yaml:sync` übernimmt sie trotzdem).
