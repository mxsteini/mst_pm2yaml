# Konfiguration

## Ordner für nicht versionierte Dateien

Der Ordner wird in dieser Reihenfolge bestimmt, der erste gesetzte Wert gilt:

1. **Site-Einstellung** `mstPm2yaml.folder` der Site, in der das Formular liegt
   (Site-Set `mstitb/mst-pm2yaml`)
2. **Extension-Konfiguration** `folder`
3. Standard: `fileadmin/_Powermail/`

Alle Pfade sind relativ zum `public`-Verzeichnis. Formulare, deren Datei aus diesem Ordner stammt, sind immer
editierbar.

### Site-Einstellung

In `config/sites/<site>/settings.yaml`:

```yaml
mstPm2yaml:
  folder: 'fileadmin/_Powermail/kundenseite/'
```

Dafür muss das Set `mstitb/mst-pm2yaml` in den `dependencies` der Site stehen
(siehe [Installation](Installation.md)).

### Extension-Konfiguration

Im Backend unter *Admin Tools → Settings → Extension Configuration → mst_pm2yaml*, oder in der
`config/system/settings.php` bzw. `additional.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['mst_pm2yaml']['folder'] = 'fileadmin/_Powermail/';
```

Der Wert gilt, wenn die Site des Formulars keinen Ordner setzt oder das Formular außerhalb jeder Site liegt.

## Versionierte Dateien

Versionierte Dateien liegen in jeder aktiven Extension unter `Configuration/Powermail/`. Es ist keine weitere
Konfiguration nötig, jede `*.yaml` dort erscheint in der Auswahl.

## Dateinamen und Sicherheit

- Erlaubt sind Dateinamen aus `A–Z`, `a–z`, `0–9`, `.`, `_` und `-` mit Endung `.yaml` oder `.yml`.
- Der Pfad muss genau auf den Ordner der Site bzw. `EXT:<extension>/Configuration/Powermail/` zeigen. Ein
  manipulierter Wert in der Datenbank (z. B. `../../etc/passwd`) wird nicht gelesen.
- Die Export-Route im Backend ist durch das Backend-Token und den Login geschützt.
