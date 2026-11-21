# Concept

Powermail stores forms in three tables (`tx_powermail_domain_model_form`, `_page` and `_field`); translations are
records of the same tables. That is convenient for editors, but impractical for development and deployment: the
data belongs to the database, not to the code.

`mst_pm2yaml` turns this around: **the file is the source, the database is a copy of it.**

```text
   File (YAML) ──── import ────▶ Database ──▶ Powermail (unchanged)
        ▲                            │
        └──────── write ◀────────────┘   (saving in the backend)
```

## When is what synchronized?

| Event | Direction | Details |
|---|---|---|
| Select a file in the form | file → database | The file wins. |
| The plugin is displayed (frontend, preview in the page module, editing plugin, form, page or field) | file → database | Only if the modification time of the file changed since the last synchronization. |
| Saving in the backend (live workspace or publishing) | database → file | Only if the file is writable. Drafts in workspaces do not write anything. |
| `typo3 pm2yaml:sync` | file → database | For example during deployment. |
| `typo3 pm2yaml:export` | database → file | Move an existing form into a file. |

## What goes into the file, what does not?

- The file contains **all columns of the Powermail tables**, including columns added by other extensions
  (for example `powermail_cond`). They are derived from the TCA and the database schema.
- **Default values are omitted**, which keeps the file short and diff-friendly.
- **uids are omitted.** Pages are matched by an *identifier*, fields by their *marker*.
- System, relation and key columns (`uid`, `pid`, `sorting`, `deleted` …) are not part of the file.

## Where do the files live?

There are two places:

| Source | Example | Editing in the backend |
|---|---|---|
| **versioned** | `EXT:my_sitepackage/Configuration/Powermail/contact.yaml` | only in the Development context, read-only otherwise |
| **site folder** (not versioned) | `fileadmin/_Powermail/contact.yaml` | always |

Without a file, the form works as before, with the database only.

> [!TIP]
> Typical workflow for a versioned form: edit in DDEV (backend or file), commit the file, deploy. On live the file
> is imported during deployment and is read-only there.
