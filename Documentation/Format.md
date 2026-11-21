# YAML format

## Structure

```yaml
title: 'Application for the test phase'
pages:
  your-details:               # identifier of the page
    title: 'Your details'
    fields:
      app:                    # marker of the field
        type: radio
        title: 'Which app are you applying for?'
        settings: |-
          Octavo Sheets
          Octavo Train
        mandatory: 1
      submit:
        type: submit
        title: 'Send'
localizations:
  1:                          # language id of the site
    title: 'Bewerbung für die Testphase'
    pages:
      your-details:
        title: 'Deine Angaben'
    fields:
      app:
        title: 'Für welche App bewirbst du dich?'
```

| Key | Meaning |
|---|---|
| columns of the form (`title`, …) | properties of the form in the default language |
| `pages` | pages, the key is the **identifier** |
| `pages.<identifier>.fields` | fields of the page, the key is the **marker** |
| `localizations.<language-id>` | deviations for one language |

## Properties

- Properties are the **columns of the Powermail tables**, including those added by other extensions
  (for example `powermail_cond`). `type: input` corresponds to the column `type` of the field.
- **Default values are omitted.** Whatever is not in the file has the default value of the database column.
- Write multi-line values (for example `settings` of selection fields) as text with `|`. Lists are not allowed as
  a value.
- An **unknown property** (for example a typo such as `lable`) is an error.

## Matching

- **Pages** are matched by the identifier. It is shown in the backend in the field *Bezeichner in der YAML-Datei*
  (identifier in the YAML file) and may contain `A–Z`, `a–z`, `0–9`, `_` and `-`.
- **Fields** are matched form-wide by the **marker** (`a–z`, `0–9`, `_`). The marker is unique within the form and
  the field needs a `type`.
- Matched records **keep their uid**, so stored mails keep pointing to their fields.
- Whatever is no longer in the file is deleted (`deleted = 1`), translations included.

## Translations

- `localizations` is keyed by the **language id of the site** (greater than 0).
- Translations contain **only deviations** from the default language.
- Every language in `localizations` gets all pages and fields, including those without a deviation of their own.
- If a translation refers to an unknown page or field, that is an error.

## All or nothing

A file is **completely validated before** anything is written to the database. If a marker is invalid, a `type` is
missing or an unknown property occurs, **nothing** is imported. The database keeps the last valid state, and the
error message names page, field and reason.
