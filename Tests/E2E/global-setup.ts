import { CONTENT_UID, DB_ONLY_FORM_UID, FORM_TABLE, FORM_UID, PAGE_UID, flexForm, removeFixture, resetFixture, sql, typo3 } from './helpers';

export default async function globalSetup(): Promise<void> {
  removeFixture();
  const now = Math.floor(Date.now() / 1000);
  const escaped = flexForm(FORM_UID).replace(/'/g, "''");
  sql([
    `INSERT INTO pages (uid, pid, title, slug, doktype, nav_hide, hidden, sorting, tstamp, crdate, perms_userid, perms_groupid, perms_user, perms_group, perms_everybody)`
      + ` VALUES (${PAGE_UID}, 1, 'E2E pm2yaml', '/e2e-pm2yaml', 1, 1, 0, 999999, ${now}, ${now}, 2, 0, 31, 27, 0)`,
    `INSERT INTO ${FORM_TABLE} (uid, pid, title, tstamp, crdate) VALUES (${FORM_UID}, ${PAGE_UID}, 'E2E Formular', ${now}, ${now})`,
    `INSERT INTO ${FORM_TABLE} (uid, pid, title, tstamp, crdate) VALUES (${DB_ONLY_FORM_UID}, ${PAGE_UID}, 'E2E Export', ${now}, ${now})`,
    `INSERT INTO tt_content (uid, pid, CType, colPos, sorting, header, pi_flexform, tstamp, crdate)`
      + ` VALUES (${CONTENT_UID}, ${PAGE_UID}, 'powermail_pi1', 0, 1, 'E2E Plugin', '${escaped}', ${now}, ${now})`,
  ].join(';') + ';');
  resetFixture();
  typo3('cache:flush');
}
