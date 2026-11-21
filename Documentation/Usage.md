# Verwendung

## Datei auswählen

Am Formular gibt es das Feld **YAML-Datei**. Zur Auswahl stehen

- alle Dateien `Configuration/Powermail/*.yaml` aller aktiven Extensions (**versioniert**),
- alle `*.yaml` und `*.yml` im Ordner der Site (**nicht versioniert**, Standard `fileadmin/_Powermail/`),
- **– nur Datenbank –**: keine Verknüpfung, Powermail arbeitet wie bisher.

Mit der Auswahl wird der Inhalt der Datei in die Datenbank übernommen, **die Datei gewinnt**.

## Ein bestehendes Formular in eine Datei überführen

Im Backend: Formular ohne Datei öffnen und neben dem Feld **YAML-Datei** auf den Export-Button klicken. Das
Formular wird in den Ordner der Site geschrieben (Dateiname aus dem Titel, bei Kollisionen `-2`, `-3` …) und
verknüpft.

Auf der Kommandozeile, auch für versionierte Dateien in einer Extension:

```bash
vendor/bin/typo3 pm2yaml:export 12 --file=EXT:mein_sitepackage/Configuration/Powermail/kontakt.yaml
```

## Im Alltag

| Aktion | Wirkung |
|---|---|
| Datei im Editor ändern, Seite im Frontend aufrufen | Datei wird übernommen, wenn sich ihre Änderungszeit geändert hat. |
| Feld, Seite oder Formular im Backend speichern | Die Datei wird neu geschrieben (nur Live-Workspace, nur wenn schreibbar). Eine Meldung bestätigt das. |
| Plugin, Formular, Seite oder Feld im Backend öffnen | Vorher wird eine geänderte Datei übernommen. Das Formular zeigt also den Stand der Datei. |
| Entwurf in einem Workspace speichern | Die Datei bleibt unverändert, erst das Veröffentlichen schreibt sie. |
| Feld in der Datei umbenennen (Marker ändern) | Es ist ein **neues** Feld. Das alte wird gelöscht, gespeicherte Mails zeigen weiter auf das alte. |

## Versionierte Formulare und Schreibschutz

Eine Datei unter `EXT:…/Configuration/Powermail/` ist **schreibgeschützt**, sobald

- der Kontext nicht `Development` ist (Staging, Production), oder
- die Extension unter `vendor/` liegt oder das Verzeichnis nicht beschreibbar ist.

Ein schreibgeschütztes Formular lässt im Backend nur noch die Dateiauswahl zu („nur Datenbank“ hebt die
Verknüpfung auf). Das Ändern, Anlegen, Löschen, Verschieben und Übersetzen von Seiten und Feldern wird abgelehnt.
Änderungen gehören in die Datei und damit ins Repository.

Dateien im Ordner der Site sind **immer** editierbar.

## Deployment

Versionierte Dateien gehen zusammen mit dem Code live. Damit die Datenbank sie übernimmt, ruft das Deployment auf:

```bash
vendor/bin/typo3 pm2yaml:sync --only-locked
```

`--only-locked` beschränkt den Lauf auf versionierte, dort schreibgeschützte Formulare. Mit `--dry-run` wird nur
geprüft. Nach einem Rollback der Dateien bei Bedarf von Hand ausführen.
