import { expect, test, type FrameLocator, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import {
  BASE_YAML, DB_ONLY_FORM_UID, FIELD_TABLE, FILE_VALUE, FOLDER, FORM_TABLE, FORM_UID,
  fieldTitle, fieldUid, readYaml, resetFixture, sqlValue, writeYaml,
} from '../helpers';

async function openRecord(page: Page, table: string, uid: number): Promise<FrameLocator> {
  await page.goto(`/typo3/record/edit?edit[${table}][${uid}]=edit&returnUrl=/typo3/`);
  const frame = page.frameLocator('#typo3-contentIframe');
  await frame.locator('form[name="editform"]').waitFor();
  return frame;
}

async function save(frame: FrameLocator): Promise<void> {
  await frame.locator('button[name="_savedok"]').click();
  await frame.locator('form[name="editform"]').waitFor();
}

/** Flash-Meldungen erscheinen nach dem Speichern als Alert im Inhalts-Iframe */
async function expectMessage(page: Page, text: string | RegExp): Promise<void> {
  await expect(page.frameLocator('#typo3-contentIframe').locator('.alert-message').filter({ hasText: text }).first()).toBeVisible();
}

const fileSelect = (frame: FrameLocator, uid: number) =>
  frame.locator(`select[name="data[${FORM_TABLE}][${uid}][tx_mstpm2yaml_file]"]`);
const exportButton = (frame: FrameLocator) => frame.locator('a[href*="mst-pm2yaml/export"]');
const fileOf = (uid: number) => sqlValue(`SELECT tx_mstpm2yaml_file FROM ${FORM_TABLE} WHERE uid = ${uid}`);

test.describe('Backend', () => {
  test.beforeEach(() => resetFixture());

  test('Dateiauswahl listet die YAML-Dateien und erklärt die Verknüpfung', async ({ page }) => {
    const frame = await openRecord(page, FORM_TABLE, FORM_UID);
    await expect(fileSelect(frame, FORM_UID)).toHaveValue(FILE_VALUE);
    await expect(fileSelect(frame, FORM_UID).locator('option', { hasText: 'e2e-pm2yaml.yaml (fileadmin/_Powermail)' })).toHaveCount(1);
    await expect(frame.getByText('werden beim Speichern in die Datei geschrieben')).toBeVisible();
  });

  test('Öffnen eines Feldes übernimmt vorher die geänderte Datei', async ({ page }) => {
    writeYaml(BASE_YAML.replace('title: Vorname', "title: 'Vorname (aus Datei)'"));
    const frame = await openRecord(page, FIELD_TABLE, fieldUid('vorname'));
    await expect(frame.locator(`[data-formengine-input-name="data[${FIELD_TABLE}][${fieldUid('vorname')}][title]"]`))
      .toHaveValue('Vorname (aus Datei)');
  });

  test('Speichern eines Feldes schreibt die Datei', async ({ page }) => {
    const uid = fieldUid('vorname');
    const frame = await openRecord(page, FIELD_TABLE, uid);
    await frame.locator(`[data-formengine-input-name="data[${FIELD_TABLE}][${uid}][title]"]`).fill('Vorname (Backend)');
    await save(frame);

    await expectMessage(page, 'geschrieben');
    expect(readYaml()).toContain("title: 'Vorname (Backend)'");
    expect(fieldTitle('vorname')).toBe('Vorname (Backend)');
  });

  test('Auswahl einer anderen Datei übernimmt deren Inhalt', async ({ page }) => {
    const other = path.join(FOLDER, 'e2e-andere.yaml');
    writeYaml(BASE_YAML.replace("title: 'E2E Formular'", "title: 'E2E Andere Datei'"), other);
    const frame = await openRecord(page, FORM_TABLE, DB_ONLY_FORM_UID);
    await fileSelect(frame, DB_ONLY_FORM_UID).selectOption('fileadmin/_Powermail/e2e-andere.yaml');
    await save(frame);

    await expectMessage(page, 'übernommen');
    expect(fileOf(DB_ONLY_FORM_UID)).toBe('fileadmin/_Powermail/e2e-andere.yaml');
    expect(sqlValue(`SELECT title FROM ${FORM_TABLE} WHERE uid = ${DB_ONLY_FORM_UID}`)).toBe('E2E Andere Datei');
    expect(fieldUid('vorname', DB_ONLY_FORM_UID)).toBeGreaterThan(0);
  });

  test('Export-Button exportiert ein Formular ohne Datei und verknüpft es', async ({ page }) => {
    const frame = await openRecord(page, FORM_TABLE, DB_ONLY_FORM_UID);
    await exportButton(frame).click();
    await frame.locator('form[name="editform"]').waitFor();

    await expectMessage(page, /Exportiert nach fileadmin\/_Powermail\/e2e-export\.yaml/);
    expect(fileOf(DB_ONLY_FORM_UID)).toBe('fileadmin/_Powermail/e2e-export.yaml');
    expect(fs.readFileSync(path.join(FOLDER, 'e2e-export.yaml'), 'utf8')).toContain("title: 'E2E Export'");
    await expect(exportButton(frame)).toHaveCount(0);
  });
});

test.describe('Backend: Negativfälle', () => {
  test.beforeEach(() => resetFixture());

  test('kein Export-Button bei verknüpftem Formular', async ({ page }) => {
    const frame = await openRecord(page, FORM_TABLE, FORM_UID);
    await expect(fileSelect(frame, FORM_UID)).toBeVisible();
    await expect(exportButton(frame)).toHaveCount(0);
  });

  test('inzwischen gelöschte Datei wird abgelehnt, Verknüpfung bleibt', async ({ page }) => {
    const temp = path.join(FOLDER, 'e2e-temp.yaml');
    writeYaml(BASE_YAML, temp);
    const frame = await openRecord(page, FORM_TABLE, FORM_UID);
    fs.rmSync(temp);
    await fileSelect(frame, FORM_UID).selectOption('fileadmin/_Powermail/e2e-temp.yaml');
    await save(frame);

    await expectMessage(page, 'Ungültige oder fehlende YAML-Datei');
    expect(fileOf(FORM_UID)).toBe(FILE_VALUE);
  });

  test('Datei mit ungültigem Inhalt: Fehlermeldung, Auswahl wird zurückgesetzt, DB unverändert', async ({ page }) => {
    const broken = path.join(FOLDER, 'e2e-ungueltig.yaml');
    writeYaml(BASE_YAML.replace('      vorname:', '      Vorname:'), broken);
    const frame = await openRecord(page, FORM_TABLE, DB_ONLY_FORM_UID);
    await fileSelect(frame, DB_ONLY_FORM_UID).selectOption('fileadmin/_Powermail/e2e-ungueltig.yaml');
    await save(frame);

    await expectMessage(page, /Ungültiger Marker "Vorname".*Auswahl zurückgesetzt/);
    expect(fileOf(DB_ONLY_FORM_UID)).toBe('');
    expect(fieldUid('vorname', DB_ONLY_FORM_UID)).toBe(0);
  });

  test('Export-Route ohne gültiges Token wird abgelehnt', async ({ page }) => {
    await page.goto('/typo3/');
    const response = await page.request.get(`/typo3/mst-pm2yaml/export?form=${DB_ONLY_FORM_UID}&returnUrl=/typo3/`, { maxRedirects: 0 });
    // TYPO3 schickt Aufrufe ohne Token zum Login, der Controller läuft nicht
    expect(response.headers()['location'] ?? '').toContain('/typo3/login');
    expect(fileOf(DB_ONLY_FORM_UID)).toBe('');
    expect(fs.existsSync(path.join(FOLDER, 'e2e-export.yaml'))).toBe(false);
  });

  test('Export-Route ohne Backend-Login wird abgelehnt', async ({ playwright, baseURL }) => {
    const anonymous = await playwright.request.newContext({ baseURL, ignoreHTTPSErrors: true });
    const response = await anonymous.get(`/typo3/mst-pm2yaml/export?form=${DB_ONLY_FORM_UID}`, { maxRedirects: 0 });
    expect(response.status()).not.toBe(200);
    expect(response.headers()['location'] ?? '').not.toContain('record/edit');
    expect(fileOf(DB_ONLY_FORM_UID)).toBe('');
    await anonymous.dispose();
  });

  test('versionierte Datei ist im Development-Kontext editierbar (Schreibschutz greift erst außerhalb)', async ({ page }) => {
    // Gegenprobe zur Sperre: in DDEV (Development) darf eine EXT-Datei geschrieben werden.
    // Die Sperre selbst (Production/Staging) prüfen die Unit-Tests (FileLocatorTest).
    // Verzeichnis der Extension selbst (liegt je nach Projekt unter packages/ oder extensions/)
    const extDir = path.resolve(__dirname, '../../../Configuration/Powermail');
    fs.mkdirSync(extDir, { recursive: true });
    try {
      writeYaml(BASE_YAML, path.join(extDir, 'e2e-versioniert.yaml'));
      const frame = await openRecord(page, FORM_TABLE, DB_ONLY_FORM_UID);
      await fileSelect(frame, DB_ONLY_FORM_UID).selectOption('EXT:mst_pm2yaml/Configuration/Powermail/e2e-versioniert.yaml');
      await save(frame);
      await expectMessage(page, 'übernommen');
      await expect(frame.locator('[name*="[title]"]').first()).toBeEditable();
    } finally {
      fs.rmSync(extDir, { recursive: true, force: true });
    }
  });
});
