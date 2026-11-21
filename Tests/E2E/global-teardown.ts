import { removeFixture, typo3 } from './helpers';

export default async function globalTeardown(): Promise<void> {
  if (process.env.E2E_KEEP_FIXTURE) {
    return;
  }
  removeFixture();
  typo3('cache:flush');
}
