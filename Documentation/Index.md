# mst_pm2yaml – Powermail forms as YAML files

> [!WARNING]
> **This extension is no longer supported.** `mst_pm2yaml` is not maintained any more: there will be no further
> features, bug fixes or compatibility updates. Use it at your own risk and consider a fork if you still depend on it.

A Powermail form – form, pages, fields and translations – lives in a **YAML file**. The extension keeps file and
database in sync. **Powermail itself stays untouched**: it reads from the database as always, and stored mails
keep pointing to their fields.

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
      submit:
        type: submit
        title: 'Send'
```

## What you gain

- **Forms in your code repository**: forms are committed, reviewed and deployed like any other code. No more
  syncing database dumps between development and live.
- **One file instead of a click-through**: reorder fields, set mandatory flags or add translations in your editor,
  with a readable diff.
- **The backend keeps working**: saving in the backend writes the file back.
- **Stable records**: fields are matched by their marker and keep their uid.
- **Safe in production**: versioned forms are read-only on live, errors in a file are logged, and the frontend
  stays available.

## At a glance

| | |
|---|---|
| Extension key | `mst_pm2yaml` |
| Composer | `mstitb/mst-pm2yaml` |
| TYPO3 | 13.4 |
| Powermail | 13.x (`in2code/powermail`) |
| PHP | 8.2 to 8.5 |
| Source | <https://github.com/mxsteini/mst_pm2yaml> |

> [!NOTE]
> The backend user interface of the extension is currently in German (for example the field **YAML-Datei**).
> This documentation names the German label and explains it where it first appears.

## Contents

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
