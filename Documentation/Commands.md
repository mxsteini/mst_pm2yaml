# Commands

## `pm2yaml:export`

Writes a form to a YAML file and links it.

```bash
vendor/bin/typo3 pm2yaml:export <form-uid> [--file=<target>]
```

| Argument / option | Meaning |
|---|---|
| `form` | uid of the form (default language) |
| `--file` | Target, for example `EXT:my_sitepackage/Configuration/Powermail/contact.yaml` (Development context only) or `fileadmin/_Powermail/contact.yaml`. Without it: the linked file, otherwise a new file in the site folder. |

```bash
$ vendor/bin/typo3 pm2yaml:export 12
Formular 12 → fileadmin/_Powermail/contact.yaml
```

## `pm2yaml:sync`

Imports the YAML files of all linked forms into the database.

```bash
vendor/bin/typo3 pm2yaml:sync [--form=<uid>] [--dry-run] [--only-locked]
```

| Option | Meaning |
|---|---|
| `--form`, `-f` | only these form uid(s), may be given multiple times |
| `--dry-run` | only check, save nothing (reports "würde geändert", i.e. would change) |
| `--only-locked` | only versioned files that are read-only in this environment (for deployment) |

For every form a line with the result is printed: `geändert` (changed), `unverändert` (unchanged) or, with
`--dry-run`, `würde geändert` (would change). On an error the message is printed and the exit code is not 0.
