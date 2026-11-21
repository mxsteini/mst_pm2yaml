# Tests

Die Extension bringt zwei Testebenen mit.

| Ebene | Werkzeug | Datenbank | Umfang |
|---|---|---|---|
| Unit | PHPUnit 11, TYPO3 Testing Framework | nein | Spalten, Ordner, Dateipfade und Schreibschutz, Import, Export |
| E2E | Playwright (Chrome) | ja, laufende DDEV-Instanz | Backend (Auswahl, Speichern, Export) und Frontend (Anzeigen, Absenden) inklusive Negativfälle |

## Voraussetzungen im Projekt

```bash
composer require --dev typo3/testing-framework:^9 phpunit/phpunit:^11.5
```

Der Test-Namespace muss im **Root**-`composer.json` des Projekts autoloadbar sein (Composer ignoriert
`autoload-dev` von Abhängigkeiten):

```json
"autoload-dev": {
    "psr-4": {
        "MSTITB\\MstPm2yaml\\Tests\\": "extensions/mst_pm2yaml/Tests/"
    }
}
```

Für die E2E-Tests braucht die DDEV-Instanz

- den **Development-Kontext** (`TYPO3_CONTEXT=Development/DDEV`),
- einen **Auto-Login** im Backend (`undkonsorten/typo3-auto-login`, ab 2.3 mit PHP 8.5 kompatibel,
  `TYPO3_AUTOLOGIN_USERNAME` zeigt auf einen Admin),
- eine Site mit **Root-Seite 1** und das Powermail-Set (`in2code/powermail-main`),
- **Mailpit** (in DDEV enthalten) für die Prüfung des Mailversands,
- Google Chrome auf dem Rechner (`E2E_CHANNEL` wählt einen anderen Browser).

## Unit-Tests

```bash
ddev exec vendor/bin/phpunit -c <extension>/Tests/phpunit.xml
```

`<extension>` ist das Verzeichnis der Extension im Projekt, z. B. `packages/mst_pm2yaml` oder
`extensions/mst_pm2yaml`.

## E2E-Tests

```bash
cd <extension>/Tests/E2E
npm ci
E2E_PROJECT_ROOT=/pfad/zum/ddev-projekt npx playwright test
```

| Variable | Bedeutung |
|---|---|
| `E2E_PROJECT_ROOT` | Verzeichnis des DDEV-Projekts (mit `.ddev/config.yaml`). Ohne Angabe wird ab der Extension nach oben gesucht, das passt für `packages/mst_pm2yaml`. |
| `E2E_BASE_URL` | Basis-URL. Ohne Angabe die primäre URL aus `ddev describe`. |
| `E2E_CHANNEL` | Browser-Kanal, Standard `chrome` |
| `E2E_KEEP_FIXTURE` | `1` lässt Testdaten nach dem Lauf stehen |

Im Repository der Extensions gibt es zusätzlich `npm run test:v13` (Projekt `v13` neben dem Extension-Ordner).

### Was die Tests anlegen

Die Tests legen eine eigene Seite `/e2e-pm2yaml` mit zwei Formularen und einem Plugin an (uids ab 9001, Dateien
`fileadmin/_Powermail/e2e-*.yaml`) und räumen alles danach wieder ab. Sie teilen sich diese Daten und laufen
deshalb mit einem Worker.

> [!WARNING]
> Die E2E-Tests schreiben in die Datenbank des Projekts. Nur gegen Entwicklungs-Instanzen laufen lassen.

### Grenzen

- Der Schreibschutz außerhalb von `Development` ist nur per Unit-Test abgedeckt, weil DDEV im
  Development-Kontext läuft.
- Die Änderungszeit einer Datei hat Sekundenauflösung. Wird eine Datei zweimal in derselben Sekunde geändert,
  nachdem dazwischen schon abgeglichen wurde, bemerkt der Abgleich beim Anzeigen die zweite Änderung nicht.
  `pm2yaml:sync` übernimmt sie trotzdem.

## Getestete Umgebungen

| TYPO3 | Powermail | PHP | Ergebnis |
|---|---|---|---|
| 13.4 | 13.3 | 8.2 | Unit und E2E grün |
| 13.4 | 13.3 | 8.5 | Unit und E2E grün |
