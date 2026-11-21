# mst_pm2yaml – Powermail-Formulare als YAML-Datei

Ein Powermail-Formular – Formular, Seiten, Felder und Übersetzungen – lebt in einer **YAML-Datei**. Die Extension
hält Datei und Datenbank synchron. **Powermail selbst bleibt unverändert**: Es liest wie immer aus der Datenbank,
gespeicherte Mails zeigen weiter auf ihre Felder.

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
      absenden:
        type: submit
        title: 'Absenden'
```

## Was du damit gewinnst

- **Formulare im Code-Repository**: Formulare werden mit dem Projekt committet, reviewt und deployt wie jeder andere
  Code. Kein Abgleich von Datenbank-Dumps zwischen Entwicklung und Live.
- **Eine Datei statt Klickstrecke**: Felder umsortieren, Pflichtfelder setzen oder Übersetzungen ergänzen geht im
  Editor und per Diff nachvollziehbar.
- **Backend funktioniert weiter**: Wer im Backend speichert, schreibt die Datei zurück.
- **Stabile Datensätze**: Felder werden über ihren Marker zugeordnet und behalten ihre uid.
- **Sicher im Betrieb**: Versionierte Formulare sind auf Live schreibgeschützt, Fehler in der Datei werden
  geloggt, das Frontend bleibt erreichbar.

## Auf einen Blick

| | |
|---|---|
| Extension-Key | `mst_pm2yaml` |
| Composer | `mstitb/mst-pm2yaml` |
| TYPO3 | 13.4 |
| Powermail | 13.x (`in2code/powermail`) |
| PHP | 8.2 bis 8.5 |
| Quelltext | <https://github.com/mxsteini/mst_pm2yaml> |

## Inhalt

```{toctree}
:maxdepth: 2
:titlesonly:

Introduction
Installation
Usage
Format
Commands
Configuration
Testing
Troubleshooting
```
