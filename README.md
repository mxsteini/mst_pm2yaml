# mst_pm2yaml – Powermail-Formulare als YAML-Datei

Ein Powermail-Formular (Formular, Seiten, Felder, Übersetzungen) wird mit einer YAML-Datei abgeglichen.
Powermail selbst bleibt unverändert: Es liest wie immer aus der Datenbank, die Extension hält Datei und
Datenbank synchron. Die Datei enthält keine uids.

```yaml
title: 'Kontakt'
pages:
  angaben:
    title: 'Deine Angaben'
    fields:
      vorname:
        type: input
        title: 'Vorname'
        mandatory: 1
```

| | |
|---|---|
| Composer | `composer require mstitb/mst-pm2yaml` |
| TYPO3 / Powermail / PHP | 13.4 / 13.x / 8.2 bis 8.5 |

## Kurzfassung

- **Datei auswählen** (Feld „YAML-Datei“ am Formular): versioniert in `EXT:…/Configuration/Powermail/`
  (im Backend nur im Development-Kontext schreibbar) oder im Ordner der Site (`fileadmin/_Powermail/`, immer
  schreibbar). Die Datei gewinnt.
- **Anzeigen** des Plugins übernimmt eine geänderte Datei vorher in die Datenbank.
- **Speichern** im Backend (Live-Workspace) schreibt die Datei zurück.
- **Kommandos**: `typo3 pm2yaml:export <uid>` und `typo3 pm2yaml:sync [--only-locked]` (Deployment).

## Dokumentation

Die ausführliche Dokumentation liegt in [Documentation/](Documentation/Index.md):
[Konzept](Documentation/Introduction.md), [Installation](Documentation/Installation.md),
[Verwendung](Documentation/Usage.md), [YAML-Format](Documentation/Format.md),
[Kommandos](Documentation/Commands.md), [Konfiguration](Documentation/Configuration.md),
[Tests](Documentation/Testing.md), [Fehlersuche](Documentation/Troubleshooting.md).

## Tests

```bash
# Unit-Tests (ohne Datenbank)
ddev exec vendor/bin/phpunit -c <extension>/Tests/phpunit.xml

# E2E-Tests gegen eine laufende DDEV-Instanz (Chrome)
cd <extension>/Tests/E2E && npm ci && E2E_PROJECT_ROOT=<ddev-projekt> npx playwright test
```

Details und Voraussetzungen: [Documentation/Testing.md](Documentation/Testing.md).
