# Konzept

Powermail speichert Formulare in drei Tabellen (`tx_powermail_domain_model_form`, `_page` und `_field`),
Übersetzungen sind Datensätze derselben Tabellen. Das ist für Redakteurinnen und Redakteure bequem, für Entwicklung und Deployment aber
unpraktisch: Die Daten gehören zur Datenbank, nicht zum Code.

`mst_pm2yaml` dreht das um: **Die Datei ist die Quelle, die Datenbank ist ein Abbild davon.**

```text
   Datei (YAML) ──── Import ────▶ Datenbank ──▶ Powermail (unverändert)
        ▲                            │
        └──────── Schreiben ◀────────┘   (Speichern im Backend)
```

## Wann wird was abgeglichen?

| Ereignis | Richtung | Details |
|---|---|---|
| Datei im Formular auswählen | Datei → Datenbank | Die Datei gewinnt. |
| Plugin wird angezeigt (Frontend, Seitenmodul-Vorschau, Bearbeiten von Plugin, Formular, Seite oder Feld) | Datei → Datenbank | Nur, wenn sich die Änderungszeit der Datei seit dem letzten Abgleich geändert hat. |
| Speichern im Backend (Live-Workspace oder Veröffentlichen) | Datenbank → Datei | Nur, wenn die Datei schreibbar ist. Entwürfe in Workspaces schreiben nichts. |
| `typo3 pm2yaml:sync` | Datei → Datenbank | Zum Beispiel im Deployment. |
| `typo3 pm2yaml:export` | Datenbank → Datei | Ein bestehendes Formular „in die Datei holen“. |

## Was gehört in die Datei, was nicht?

- Enthalten sind **alle Spalten der Powermail-Tabellen**, auch solche aus anderen Extensions
  (z. B. `powermail_cond`). Sie werden aus dem TCA und dem Datenbankschema ermittelt.
- **Standardwerte fehlen** in der Datei, sie bleibt dadurch kurz und diff-freundlich.
- **uids fehlen**. Seiten werden über einen *Bezeichner*, Felder über ihren *Marker* zugeordnet.
- Nicht enthalten sind System-, Relations- und Schlüsselspalten (`uid`, `pid`, `sorting`, `deleted` …).

## Wo liegen die Dateien?

Zwei Orte stehen zur Auswahl:

| Quelle | Beispiel | Bearbeiten im Backend |
|---|---|---|
| **versioniert** | `EXT:mein_sitepackage/Configuration/Powermail/kontakt.yaml` | nur im Development-Kontext, sonst schreibgeschützt |
| **Ordner der Site** (nicht versioniert) | `fileadmin/_Powermail/kontakt.yaml` | immer |

Ohne Datei arbeitet das Formular wie bisher nur mit der Datenbank.

> [!TIP]
> Typischer Ablauf für ein versioniertes Formular: in DDEV bearbeiten (Backend oder Datei), Datei committen,
> deployen. Auf Live wird die Datei beim Deployment importiert und ist dort schreibgeschützt.
