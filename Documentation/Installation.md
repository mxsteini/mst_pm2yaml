# Installation

## Requirements

- TYPO3 13.4, PHP 8.2 to 8.5
- Powermail 13 (`in2code/powermail`)
- A Composer-based installation

## Steps

1. **Install the extension**

   ```bash
   composer require mstitb/mst-pm2yaml
   ```

2. **Update the database schema.** The extension adds three columns:

   | Table | Column | Purpose |
   |---|---|---|
   | `tx_powermail_domain_model_form` | `tx_mstpm2yaml_file` | selected YAML file |
   | `tx_powermail_domain_model_form` | `tx_mstpm2yaml_synced` | modification time of the file at the last synchronization |
   | `tx_powermail_domain_model_page` | `tx_mstpm2yaml_identifier` | identifier of the page in the file |

   ```bash
   vendor/bin/typo3 extension:setup
   vendor/bin/typo3 database:updateschema
   ```

3. **Include the site set (optional).** Only needed if a site should get its own folder for the files. In the
   `config.yaml` of the site:

   ```yaml
   dependencies:
     - mstitb/mst-pm2yaml
   ```

   The setting `mstPm2yaml.folder` is then available in the site settings editor
   (see [Configuration](Configuration.md)).

4. **Flush the caches.**

   ```bash
   vendor/bin/typo3 cache:flush
   ```

## Verify

After the title, the Powermail form shows the field **YAML-Datei** (YAML file). Without a selection everything
works as before.

> [!NOTE]
> If the folder for the files is located below `public/` (default: `public/fileadmin/_Powermail/`), the extension
> creates a `.htaccess` containing `Require all denied` when it first writes there. YAML files are then not
> accessible via HTTP. With Nginx you have to block the folder yourself.
