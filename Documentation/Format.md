# YAML-Format

## Aufbau

```yaml
title: 'Bewerbung für die Testphase'
pages:
  deine-angaben:              # Bezeichner der Seite
    title: 'Deine Angaben'
    fields:
      app:                    # Marker des Feldes
        type: radio
        title: 'Für welche App bewirbst du dich?'
        settings: |-
          Octavo Sheets
          Octavo Train
        mandatory: 1
      absenden:
        type: submit
        title: 'Absenden'
localizations:
  1:                          # Sprach-ID der Site
    title: 'Application for the test phase'
    pages:
      deine-angaben:
        title: 'Your details'
    fields:
      app:
        title: 'Which app are you applying for?'
```

| Schlüssel | Bedeutung |
|---|---|
| Spalten des Formulars (`title`, …) | Eigenschaften des Formulars in der Standardsprache |
| `pages` | Seiten, Schlüssel ist der **Bezeichner** |
| `pages.<bezeichner>.fields` | Felder der Seite, Schlüssel ist der **Marker** |
| `localizations.<sprach-id>` | Abweichungen für eine Sprache |

## Eigenschaften

- Eigenschaften sind die **Spalten der Powermail-Tabellen**, auch aus anderen Extensions
  (z. B. `powermail_cond`). `type: input` entspricht der Spalte `type` am Feld.
- **Standardwerte fehlen.** Was nicht in der Datei steht, hat den Standardwert der Datenbankspalte.
- Mehrzeilige Werte (z. B. `settings` bei Auswahlfeldern) schreibst du als Text mit `|`. Listen sind als Wert nicht
  erlaubt.
- Eine **unbekannte Eigenschaft** (z. B. ein Tippfehler wie `lable`) ist ein Fehler.

## Zuordnung

- **Seiten** werden über den Bezeichner zugeordnet. Er steht im Backend im Feld *Bezeichner in der YAML-Datei*
  und darf `A–Z`, `a–z`, `0–9`, `_` und `-` enthalten.
- **Felder** werden formularweit über den **Marker** zugeordnet (`a–z`, `0–9`, `_`). Der Marker ist im Formular
  eindeutig und das Feld braucht einen `type`.
- Zugeordnete Datensätze **behalten ihre uid**. Gespeicherte Mails zeigen also weiter auf ihre Felder.
- Was nicht mehr in der Datei steht, wird gelöscht (`deleted = 1`), Übersetzungen eingeschlossen.

## Übersetzungen

- `localizations` ist nach **Sprach-ID der Site** geschlüsselt (größer als 0).
- Übersetzungen enthalten **nur Abweichungen** von der Standardsprache.
- Jede Sprache in `localizations` bekommt alle Seiten und Felder, auch solche ohne eigene Abweichung.
- Verweist eine Übersetzung auf eine unbekannte Seite oder ein unbekanntes Feld, ist das ein Fehler.

## Alles oder nichts

Eine Datei wird **vollständig geprüft, bevor** etwas in die Datenbank geschrieben wird. Ist ein Marker ungültig,
fehlt ein `type` oder steht eine unbekannte Eigenschaft in der Datei, wird **nichts** übernommen. Die Datenbank
behält den letzten gültigen Stand, die Fehlermeldung nennt Seite, Feld und Grund.
