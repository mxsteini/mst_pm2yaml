# Kommandos

## `pm2yaml:export`

Schreibt ein Formular in eine YAML-Datei und verknüpft es damit.

```bash
vendor/bin/typo3 pm2yaml:export <form-uid> [--file=<ziel>]
```

| Argument / Option | Bedeutung |
|---|---|
| `form` | uid des Formulars (Standardsprache) |
| `--file` | Ziel, z. B. `EXT:mein_sitepackage/Configuration/Powermail/kontakt.yaml` (nur im Development-Kontext) oder `fileadmin/_Powermail/kontakt.yaml`. Ohne Angabe: die verknüpfte Datei, sonst eine neue Datei im Ordner der Site. |

```bash
$ vendor/bin/typo3 pm2yaml:export 12
Formular 12 → fileadmin/_Powermail/kontakt.yaml
```

## `pm2yaml:sync`

Übernimmt die YAML-Dateien aller verknüpften Formulare in die Datenbank.

```bash
vendor/bin/typo3 pm2yaml:sync [--form=<uid>] [--dry-run] [--only-locked]
```

| Option | Bedeutung |
|---|---|
| `--form`, `-f` | nur diese Formular-uid(s), mehrfach angebbar |
| `--dry-run` | nur prüfen, nichts speichern („würde geändert“) |
| `--only-locked` | nur versionierte Dateien, die in dieser Umgebung schreibgeschützt sind (für das Deployment) |

Pro Formular erscheint eine Zeile mit dem Ergebnis `geändert`, `unverändert` oder – bei `--dry-run` –
`würde geändert`. Bei einem Fehler wird die Meldung ausgegeben und der Exit-Code ist ungleich 0.
