import { expect, test } from '@playwright/test';
import {
  BASE_YAML, FILE_VALUE, FORM_TABLE, FORM_UID, PAGE_UID, PAGE_URL,
  ddev, fieldTitle, fieldUid, resetFixture, sql, sqlValue, writeYaml,
} from '../helpers';

const field = (marker: string) => `[name="tx_powermail_pi1[field][${marker}]"]`;
/** Das Projekt speichert Mails nicht (db.enable = 0), geprüft wird der Versand über Mailpit in DDEV */
const sentMails = (text: string): number => JSON.parse(
  ddev(['exec', 'curl', '-s', `http://127.0.0.1:8025/api/v1/search?query=${encodeURIComponent(text)}`])
).messages_count;

test.describe('Frontend: Datei → Datenbank beim Anzeigen des Plugins', () => {
  test.beforeEach(() => resetFixture());

  test('zeigt das Formular aus der YAML-Datei', async ({ page }) => {
    const response = await page.goto(PAGE_URL);
    expect(response?.status()).toBe(200);
    await expect(page.locator('legend', { hasText: 'Angaben' })).toBeVisible();
    await expect(page.locator(field('vorname'))).toHaveAttribute('required', 'required');
    await expect(page.locator(field('email'))).toHaveAttribute('type', 'email');
    await expect(page.locator('label[for="powermail_field_vorname"]')).toContainText('Vorname');
  });

  test('übernimmt eine geänderte Datei beim nächsten Aufruf, uids bleiben', async ({ page }) => {
    const vorname = fieldUid('vorname');
    writeYaml(BASE_YAML
      .replace('title: Vorname', "title: 'Vorname (geändert)'")
      .replace('      absenden:', '      telefon:\n        type: input\n        title: Telefon\n      absenden:'));

    await page.goto(PAGE_URL);
    await expect(page.locator('label[for="powermail_field_vorname"]')).toContainText('Vorname (geändert)');
    await expect(page.locator(field('telefon'))).toBeVisible();
    expect(fieldUid('vorname')).toBe(vorname);
  });

  test('entfernt Felder, die nicht mehr in der Datei stehen', async ({ page }) => {
    writeYaml(BASE_YAML.replace(/      email:\n(        .*\n)+?(?=      absenden:)/, ''));
    await page.goto(PAGE_URL);
    await expect(page.locator(field('vorname'))).toBeVisible();
    await expect(page.locator(field('email'))).toHaveCount(0);
  });

  test('unveränderte Datei wird nicht erneut importiert', async ({ page }) => {
    await page.goto(PAGE_URL);
    const tstamp = sqlValue(`SELECT MAX(tstamp) FROM tx_powermail_domain_model_field WHERE pid = ${PAGE_UID}`);
    sql(`UPDATE tx_powermail_domain_model_field SET title = 'Nur in der DB' WHERE uid = ${fieldUid('vorname')}`);
    await page.goto(PAGE_URL);
    expect(fieldTitle('vorname')).toBe('Nur in der DB');
    expect(sqlValue(`SELECT MAX(tstamp) FROM tx_powermail_domain_model_field WHERE pid = ${PAGE_UID} AND title <> 'Nur in der DB'`)).toBe(tstamp);
  });

  test('Absenden verschickt die Mail mit den Feldern aus der Datei', async ({ page }) => {
    const name = `Erika-${Date.now()}`;
    await page.goto(PAGE_URL);
    await page.locator(field('vorname')).fill(name);
    await page.locator(field('email')).fill('erika@example.org');
    await page.getByRole('button', { name: 'Absenden' }).click();

    await expect(page.getByText('E2E vielen Dank')).toBeVisible();
    await expect.poll(() => sentMails(name)).toBeGreaterThan(0);
  });
});

test.describe('Frontend: Negativfälle', () => {
  test.beforeEach(() => resetFixture());

  test('leeres Pflichtfeld wird serverseitig abgelehnt, keine Mail', async ({ page }) => {
    const marker = `Leer-${Date.now()}`;
    await page.goto(PAGE_URL);
    // Browser-Validierung umgehen, damit Powermail selbst prüfen muss
    await page.locator(field('vorname')).evaluate((input) => input.removeAttribute('required'));
    await page.locator(field('email')).fill(`${marker}@example.org`);
    await page.getByRole('button', { name: 'Absenden' }).click();

    await expect(page.locator(field('vorname'))).toBeVisible();
    await expect(page.getByText('E2E vielen Dank')).toHaveCount(0);
    expect(sentMails(marker)).toBe(0);
  });

  test('ungültige E-Mail (validation aus der Datei) wird abgelehnt, keine Mail', async ({ page }) => {
    const name = `Ungueltig-${Date.now()}`;
    await page.goto(PAGE_URL);
    await page.locator(field('email')).evaluate((input) => input.setAttribute('type', 'text'));
    await page.locator(field('vorname')).fill(name);
    await page.locator(field('email')).fill('keine-mail');
    await page.getByRole('button', { name: 'Absenden' }).click();

    await expect(page.locator(field('email'))).toBeVisible();
    await expect(page.getByText('E2E vielen Dank')).toHaveCount(0);
    expect(sentMails(name)).toBe(0);
  });

  test('kaputte YAML-Datei: Seite bleibt erreichbar, letzter Stand bleibt', async ({ page }) => {
    writeYaml(BASE_YAML.replace('title: Vorname', 'title: Kaputt') + 'pages: [nicht geschlossen\n');
    const response = await page.goto(PAGE_URL);
    expect(response?.status()).toBe(200);
    await expect(page.locator('label[for="powermail_field_vorname"]')).toContainText('Vorname');
    expect(fieldTitle('vorname')).toBe('Vorname');
  });

  test('ungültiger Marker: nichts wird übernommen (alles oder nichts)', async ({ page }) => {
    writeYaml(BASE_YAML
      .replace('title: Vorname', "title: 'Vorname (neu)'")
      .replace('      absenden:', '      Ungueltig:\n        type: input\n      absenden:'));
    await page.goto(PAGE_URL);
    await expect(page.locator('label[for="powermail_field_vorname"]')).not.toContainText('(neu)');
    expect(fieldTitle('vorname')).toBe('Vorname');
  });

  test('unbekannte Eigenschaft (Tippfehler): nichts wird übernommen', async ({ page }) => {
    writeYaml(BASE_YAML.replace('        title: Vorname\n', "        title: 'Vorname (neu)'\n        lable: Tippfehler\n"));
    await page.goto(PAGE_URL);
    expect(fieldTitle('vorname')).toBe('Vorname');
  });

  test('Feld ohne type: nichts wird übernommen', async ({ page }) => {
    writeYaml(BASE_YAML.replace('      absenden:', '      ohnetyp:\n        title: Ohne Typ\n      absenden:'));
    await page.goto(PAGE_URL);
    await expect(page.locator(field('ohnetyp'))).toHaveCount(0);
    expect(fieldUid('ohnetyp')).toBe(0);
  });

  test('gelöschte Datei: Formular bleibt auf dem Stand der Datenbank', async ({ page }) => {
    sql(`UPDATE ${FORM_TABLE} SET tx_mstpm2yaml_file = 'fileadmin/_Powermail/e2e-gibtsnicht.yaml' WHERE uid = ${FORM_UID}`);
    const response = await page.goto(PAGE_URL);
    expect(response?.status()).toBe(200);
    await expect(page.locator(field('vorname'))).toBeVisible();
  });

  test('manipulierter Dateipfad in der DB wird nicht gelesen', async ({ page }) => {
    sql(`UPDATE ${FORM_TABLE} SET tx_mstpm2yaml_file = 'fileadmin/_Powermail/../../../../etc/passwd' WHERE uid = ${FORM_UID}`);
    const response = await page.goto(PAGE_URL);
    expect(response?.status()).toBe(200);
    await expect(page.locator(field('vorname'))).toBeVisible();
    expect(fieldTitle('vorname')).toBe('Vorname');
  });

  test('YAML-Dateien und .htaccess sind per HTTP gesperrt', async ({ request }) => {
    expect((await request.get('/' + FILE_VALUE)).status()).toBe(403);
    expect((await request.get('/fileadmin/_Powermail/.htaccess')).status()).toBe(403);
  });
});
