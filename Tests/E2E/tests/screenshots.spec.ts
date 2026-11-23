import { test } from '@playwright/test';
import path from 'node:path';
import { DB_ONLY_FORM_UID, FORM_TABLE, resetFixture } from '../helpers';

/**
 * Erzeugt die Screenshots der Dokumentation (Documentation/Images/).
 *   E2E_SCREENSHOTS=1 E2E_PROJECT_ROOT=<ddev-projekt> npx playwright test screenshots
 */
const IMAGES = path.resolve(__dirname, '../../../Documentation/Images');

test.describe('Screenshots', () => {
  test.skip(!process.env.E2E_SCREENSHOTS, 'nur mit E2E_SCREENSHOTS=1');
  test.use({ viewport: { width: 1400, height: 900 }, deviceScaleFactor: 1 });
  test.beforeEach(() => resetFixture());

  test('Formular mit Dateiauswahl und Export-Button', async ({ page }) => {
    // Formular ohne Datei: Auswahl steht auf "nur Datenbank", der Export-Button ist sichtbar
    await page.goto(`/typo3/record/edit?edit[${FORM_TABLE}][${DB_ONLY_FORM_UID}]=edit&returnUrl=/typo3/`);
    const frame = page.frameLocator('#typo3-contentIframe');
    await frame.locator('form[name="editform"]').waitFor();
    await frame.locator('a[href*="mst-pm2yaml/export"]').waitFor();
    // nur den Inhaltsbereich aufnehmen (ohne Modulmenü und Instanzname)
    const box = (await page.locator('#typo3-contentIframe').boundingBox())!;
    await page.screenshot({
      path: path.join(IMAGES, 'form-file-selector.png'),
      clip: { x: box.x, y: box.y, width: box.width, height: 480 },
    });
  });
});
