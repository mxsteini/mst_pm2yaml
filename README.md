# mst_pm2yaml – Powermail forms as YAML files

A Powermail form (form, pages, fields, translations) is synchronized with a YAML file. Powermail itself stays
untouched: it reads from the database as always, the extension keeps file and database in sync. The file contains
no uids.

```yaml
title: 'Contact'
pages:
  details:
    title: 'Your details'
    fields:
      firstname:
        type: input
        title: 'First name'
        mandatory: 1
```

| | |
|---|---|
| Composer | `composer require mstitb/mst-pm2yaml` |
| TYPO3 / Powermail / PHP | 13.4 / 13.x / 8.2 to 8.5 |

## In short

- **Select a file** (field "YAML-Datei" on the form): versioned in `EXT:…/Configuration/Powermail/` (writable in
  the backend only in the Development context) or in the site folder (`fileadmin/_Powermail/`, always writable).
  The file wins.
- **Displaying** the plugin imports a changed file into the database first.
- **Saving** in the backend (live workspace) writes the file back.
- **Commands**: `typo3 pm2yaml:export <uid>` and `typo3 pm2yaml:sync [--only-locked]` (deployment).

## Documentation

The full documentation is in [Documentation/](Documentation/Index.md):
[Concept](Documentation/Introduction.md), [Installation](Documentation/Installation.md),
[Usage](Documentation/Usage.md), [YAML format](Documentation/Format.md),
[Commands](Documentation/Commands.md), [Configuration](Documentation/Configuration.md),
[Testing](Documentation/Testing.md), [Troubleshooting](Documentation/Troubleshooting.md).

## Tests

```bash
# Unit tests (no database)
ddev exec vendor/bin/phpunit -c <extension>/Tests/phpunit.xml

# E2E tests against a running DDEV instance (Chrome)
cd <extension>/Tests/E2E && npm ci && E2E_PROJECT_ROOT=<ddev-project> npx playwright test
```

Details and requirements: [Documentation/Testing.md](Documentation/Testing.md).
