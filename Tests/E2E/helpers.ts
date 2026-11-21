import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * DDEV-Projekt, gegen das getestet wird.
 *   E2E_PROJECT_ROOT=/pfad/zum/ddev-projekt  (Verzeichnis mit .ddev/config.yaml)
 * Ohne Angabe wird ab dem Extension-Verzeichnis nach oben das erste Verzeichnis mit .ddev/config.yaml gesucht
 * (passt, wenn die Extension unter packages/ des Projekts liegt).
 */
function findProjectRoot(): string {
  if (process.env.E2E_PROJECT_ROOT) {
    return path.resolve(process.env.E2E_PROJECT_ROOT);
  }
  let dir = __dirname;
  while (dir !== path.dirname(dir)) {
    if (fs.existsSync(path.join(dir, '.ddev', 'config.yaml'))) {
      return dir;
    }
    dir = path.dirname(dir);
  }
  throw new Error('Kein DDEV-Projekt gefunden, bitte E2E_PROJECT_ROOT setzen.');
}

export const PROJECT_ROOT = findProjectRoot();
export const FOLDER = path.join(PROJECT_ROOT, 'public/fileadmin/_Powermail');
export const FILE_VALUE = 'fileadmin/_Powermail/e2e-pm2yaml.yaml';
export const FILE_PATH = path.join(PROJECT_ROOT, 'public', FILE_VALUE);
export const PAGE_UID = 9001;
export const FORM_UID = 9001;
export const DB_ONLY_FORM_UID = 9002;
export const CONTENT_UID = 9001;
export const PAGE_URL = '/e2e-pm2yaml';

export const FORM_TABLE = 'tx_powermail_domain_model_form';
export const PAGE_TABLE = 'tx_powermail_domain_model_page';
export const FIELD_TABLE = 'tx_powermail_domain_model_field';

export function ddev(args: string[]): string {
  return execFileSync('ddev', args, { cwd: PROJECT_ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
}

/** Haupt-URL des DDEV-Projekts (E2E_BASE_URL überschreibt) */
export function baseUrl(): string {
  if (process.env.E2E_BASE_URL) {
    return process.env.E2E_BASE_URL;
  }
  const info = JSON.parse(ddev(['describe', '-j']));
  return info.raw.primary_url;
}

export function sql(query: string): string {
  return ddev(['mysql', '-N', '-B', '-e', query]).trim();
}

export function sqlValue(query: string): string {
  return sql(query).split('\n')[0] ?? '';
}

export function typo3(...args: string[]): string {
  return ddev(['exec', 'vendor/bin/typo3', ...args]);
}

let lastMtime = 0;

/**
 * Schreibt eine YAML-Datei mit einer Änderungszeit, die sicher von der bisherigen abweicht
 * (filemtime hat Sekundenauflösung, mehrere Schreibvorgänge pro Sekunde wären sonst gleich alt).
 */
export function writeYaml(content: string, file: string = FILE_PATH): void {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  const previous = fs.existsSync(file) ? Math.floor(fs.statSync(file).mtimeMs / 1000) : 0;
  fs.writeFileSync(file, content);
  const mtime = Math.max(Math.floor(Date.now() / 1000), lastMtime + 1, previous + 1);
  fs.utimesSync(file, mtime, mtime);
  lastMtime = mtime;
}

export function readYaml(file: string = FILE_PATH): string {
  return fs.readFileSync(file, 'utf8');
}

export function fieldUid(marker: string, formUid: number = FORM_UID): number {
  return Number(sqlValue(
    `SELECT f.uid FROM ${FIELD_TABLE} f JOIN ${PAGE_TABLE} p ON p.uid = f.page `
    + `WHERE p.form = ${formUid} AND f.marker = '${marker}' AND f.deleted = 0 AND f.sys_language_uid = 0`
  ));
}

export function fieldTitle(marker: string): string {
  return sqlValue(`SELECT title FROM ${FIELD_TABLE} WHERE uid = ${fieldUid(marker)}`);
}

export const BASE_YAML = `title: 'E2E Formular'
pages:
  angaben:
    title: Angaben
    fields:
      vorname:
        type: input
        title: Vorname
        mandatory: 1
      email:
        type: input
        title: E-Mail
        validation: 1
        sender_email: 1
      absenden:
        type: submit
        title: Absenden
`;

/**
 * Testdaten auf den Ausgangszustand: Datei BASE_YAML, Formular 9001 importiert, Formular 9002 ohne Datei.
 */
export function resetFixture(): void {
  for (const name of fs.existsSync(FOLDER) ? fs.readdirSync(FOLDER) : []) {
    if (name.startsWith('e2e-') && name !== path.basename(FILE_PATH)) {
      fs.rmSync(path.join(FOLDER, name));
    }
  }
  writeYaml(BASE_YAML);
  sql([
    `UPDATE ${FORM_TABLE} SET tx_mstpm2yaml_file = '${FILE_VALUE}', tx_mstpm2yaml_synced = 0 WHERE uid = ${FORM_UID}`,
    // Formular 9002: nur Datenbank, ohne Seiten
    `UPDATE ${FORM_TABLE} SET title = 'E2E Export', pages = 0, tx_mstpm2yaml_file = '', tx_mstpm2yaml_synced = 0 WHERE uid = ${DB_ONLY_FORM_UID}`,
    `DELETE f FROM ${FIELD_TABLE} f JOIN ${PAGE_TABLE} p ON p.uid = f.page WHERE p.form = ${DB_ONLY_FORM_UID}`,
    `DELETE FROM ${PAGE_TABLE} WHERE form = ${DB_ONLY_FORM_UID}`,
  ].join(';') + ';');
  typo3('pm2yaml:sync', `--form=${FORM_UID}`);
}

export function removeFixture(): void {
  sql([
    `DELETE FROM tx_powermail_domain_model_answer WHERE pid = ${PAGE_UID}`,
    `DELETE FROM tx_powermail_domain_model_mail WHERE pid = ${PAGE_UID}`,
    `DELETE FROM ${FIELD_TABLE} WHERE pid = ${PAGE_UID}`,
    `DELETE FROM ${PAGE_TABLE} WHERE pid = ${PAGE_UID}`,
    `DELETE FROM ${FORM_TABLE} WHERE pid = ${PAGE_UID}`,
    `DELETE FROM tt_content WHERE pid = ${PAGE_UID}`,
    `DELETE FROM pages WHERE uid = ${PAGE_UID}`,
    `DELETE FROM sys_refindex WHERE recuid >= 9000 AND tablename IN ('pages', 'tt_content')`,
  ].join(';') + ';');
  for (const name of fs.existsSync(FOLDER) ? fs.readdirSync(FOLDER) : []) {
    if (name.startsWith('e2e-')) {
      fs.rmSync(path.join(FOLDER, name));
    }
  }
}

export function flexForm(formUid: number): string {
  const field = (name: string, value: string | number) =>
    `<field index="settings.flexform.${name}"><value index="vDEF">${value}</value></field>`;
  const sheet = (name: string, fields: string[]) =>
    `<sheet index="${name}"><language index="lDEF">${fields.join('')}</language></sheet>`;
  return '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data>'
    + sheet('main', [field('main.form', formUid), field('main.confirmation', 0), field('main.optin', 0), field('main.moresteps', 0), field('main.pid', '')])
    + sheet('receiver', [field('receiver.type', 0), field('receiver.name', 'E2E'), field('receiver.email', 'e2e@example.org'), field('receiver.subject', 'E2E'), field('receiver.body', '{powermail_all}')])
    + sheet('sender', [field('sender.name', 'E2E'), field('sender.email', 'e2e@example.org'), field('sender.subject', 'E2E')])
    + sheet('thx', [field('thx.body', 'E2E vielen Dank')])
    + '</data></T3FlexForms>';
}
