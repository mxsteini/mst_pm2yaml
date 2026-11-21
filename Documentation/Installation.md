# Installation

## Voraussetzungen

- TYPO3 13.4, PHP 8.2 bis 8.5
- Powermail 13 (`in2code/powermail`)
- Composer-Installation (die Extension ist für Composer-Projekte gedacht)

## Schritte

1. **Extension installieren**

   ```bash
   composer require mstitb/mst-pm2yaml
   ```

2. **Datenbankschema aktualisieren.** Die Extension ergänzt drei Spalten:

   | Tabelle | Spalte | Zweck |
   |---|---|---|
   | `tx_powermail_domain_model_form` | `tx_mstpm2yaml_file` | gewählte YAML-Datei |
   | `tx_powermail_domain_model_form` | `tx_mstpm2yaml_synced` | Änderungszeit der Datei beim letzten Abgleich |
   | `tx_powermail_domain_model_page` | `tx_mstpm2yaml_identifier` | Bezeichner der Seite in der Datei |

   ```bash
   vendor/bin/typo3 extension:setup
   vendor/bin/typo3 database:updateschema
   ```

3. **Site-Set einbinden (optional).** Nur nötig, wenn eine Site einen eigenen Ordner für die Dateien bekommen
   soll. In der `config.yaml` der Site:

   ```yaml
   dependencies:
     - mstitb/mst-pm2yaml
   ```

   Danach steht im Site-Einstellungs-Editor die Einstellung `mstPm2yaml.folder` bereit
   (siehe [Konfiguration](Configuration.md)).

4. **Cache leeren.**

   ```bash
   vendor/bin/typo3 cache:flush
   ```

## Prüfen

Am Powermail-Formular erscheint nach dem Titel das Feld **YAML-Datei**. Ohne Auswahl bleibt alles wie bisher.

> [!NOTE]
> Liegt der Ordner für die Dateien unter `public/` (Standard: `public/fileadmin/_Powermail/`), legt die Extension
> dort beim ersten Schreiben automatisch eine `.htaccess` mit `Require all denied` an. YAML-Dateien sind damit
> nicht per HTTP abrufbar. Bei Nginx musst du den Ordner selbst sperren.
