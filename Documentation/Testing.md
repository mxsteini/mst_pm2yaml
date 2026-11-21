# Testing

The extension ships with two levels of tests.

| Level | Tool | Database | Scope |
|---|---|---|---|
| Unit | PHPUnit 11, TYPO3 Testing Framework | no | columns, folders, file paths and write protection, import, export |
| E2E | Playwright (Chrome) | yes, running DDEV instance | backend (selection, saving, export) and frontend (display, submit), including negative cases |

## Requirements in the project

```bash
composer require --dev typo3/testing-framework:^9 phpunit/phpunit:^11.5
```

The test namespace has to be autoloadable from the **root** `composer.json` of the project (Composer ignores
`autoload-dev` of dependencies):

```json
"autoload-dev": {
    "psr-4": {
        "MSTITB\\MstPm2yaml\\Tests\\": "extensions/mst_pm2yaml/Tests/"
    }
}
```

For the E2E tests the DDEV instance needs

- the **Development context** (`TYPO3_CONTEXT=Development/DDEV`),
- a backend **auto login** (`undkonsorten/typo3-auto-login`, compatible with PHP 8.5 from 2.3 on;
  `TYPO3_AUTOLOGIN_USERNAME` points to an admin user),
- a site with **root page 1** and the Powermail set (`in2code/powermail-main`),
- **Mailpit** (part of DDEV) to verify that mails are sent,
- Google Chrome on the machine (`E2E_CHANNEL` selects another browser).

## Unit tests

```bash
ddev exec vendor/bin/phpunit -c <extension>/Tests/phpunit.xml
```

`<extension>` is the directory of the extension in the project, for example `packages/mst_pm2yaml` or
`extensions/mst_pm2yaml`.

## E2E tests

```bash
cd <extension>/Tests/E2E
npm ci
E2E_PROJECT_ROOT=/path/to/ddev-project npx playwright test
```

| Variable | Meaning |
|---|---|
| `E2E_PROJECT_ROOT` | Directory of the DDEV project (containing `.ddev/config.yaml`). Without it, the tests search upwards from the extension, which works for `packages/mst_pm2yaml`. |
| `E2E_BASE_URL` | Base URL. Without it, the primary URL from `ddev describe`. |
| `E2E_CHANNEL` | Browser channel, default `chrome` |
| `E2E_KEEP_FIXTURE` | `1` keeps the test data after the run |

In the extensions repository there is additionally `npm run test:v13` (project `v13` next to the extension folder).

### What the tests create

The tests create their own page `/e2e-pm2yaml` with two forms and a plugin (uids from 9001, files
`fileadmin/_Powermail/e2e-*.yaml`) and remove everything afterwards. They share this data and therefore run with a
single worker.

> [!WARNING]
> The E2E tests write to the database of the project. Run them against development instances only.

### Limits

- Write protection outside of `Development` is only covered by unit tests, because DDEV runs in the Development
  context.
- The modification time of a file has a resolution of one second. If a file is changed twice within the same
  second after a synchronization in between, the synchronization on display does not notice the second change.
  `pm2yaml:sync` picks it up anyway.

## Tested environments

| TYPO3 | Powermail | PHP | Result |
|---|---|---|---|
| 13.4 | 13.3 | 8.2 | unit and E2E green |
| 13.4 | 13.3 | 8.5 | unit and E2E green |
