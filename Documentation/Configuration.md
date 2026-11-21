# Configuration

## Folder for non-versioned files

The folder is determined in this order, the first value that is set wins:

1. **Site setting** `mstPm2yaml.folder` of the site the form belongs to (site set `mstitb/mst-pm2yaml`)
2. **Extension configuration** `folder`
3. Default: `fileadmin/_Powermail/`

All paths are relative to the `public` directory. Forms whose file comes from this folder are always editable.

### Site setting

In `config/sites/<site>/settings.yaml`:

```yaml
mstPm2yaml:
  folder: 'fileadmin/_Powermail/customer-site/'
```

This requires the set `mstitb/mst-pm2yaml` in the `dependencies` of the site
(see [Installation](Installation.md)).

### Extension configuration

In the backend under *Admin Tools → Settings → Extension Configuration → mst_pm2yaml*, or in
`config/system/settings.php` / `additional.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['mst_pm2yaml']['folder'] = 'fileadmin/_Powermail/';
```

The value applies if the site of the form does not set a folder or the form is outside of any site.

## Versioned files

Versioned files live in every active extension under `Configuration/Powermail/`. No further configuration is
needed, every `*.yaml` there shows up in the selection.

## File names and security

- File names may consist of `A–Z`, `a–z`, `0–9`, `.`, `_` and `-` and must end in `.yaml` or `.yml`.
- The path must point exactly to the site folder or to `EXT:<extension>/Configuration/Powermail/`. A manipulated
  value in the database (for example `../../etc/passwd`) is not read.
- The export route in the backend is protected by the backend token and the login.
