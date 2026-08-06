import { test, expect } from '@du_pw/test';
import { drush } from '@du_pw/support/drush';

/**
 * Manual fallback and behavior contract:
 * ../../../docs/livewhale-uat.md
 */
test.describe('@du_livewhale_events - LiveWhale event embeds', () => {
  const expectedOptions =
    'id=11&format=html&group=Lamont School of Music %26 Theatre|Newman Center';

  let pageNid = '';

  test.beforeAll(async () => {
    const paragraphIds: string[] = [];

    for (let index = 0; index < 2; index++) {
      const paragraphData = JSON.stringify({
        type: 'du_livewhale_events',
        field_du_livewhale_widget_id: 11,
        field_du_livewhale_groups: [
          'Lamont School of Music & Theatre',
          'Newman Center',
        ],
      });

      paragraphIds.push(
        drush(`du:create-paragraph --data="${paragraphData}"`),
      );
    }

    const nodeData = JSON.stringify({
      type: 'page',
      title: `LiveWhale Playwright test ${Date.now()}`,
      field_page_content: paragraphIds,
    });

    pageNid = drush(`du:create-node --data="${nodeData}"`);
  });

  test('LW1 - renders events from the configured LiveWhale service', async ({ page }) => {
    const loaderResponsePromise = page.waitForResponse((response) =>
      response.url().endsWith('/livewhale/theme/core/scripts/lwcw.js'),
    );

    await page.goto(`/node/${pageNid}`);

    const loaderResponse = await loaderResponsePromise;
    expect(loaderResponse.ok()).toBe(true);

    const widgets = page.locator('.lwcw');
    await expect(widgets).toHaveCount(2);

    for (const widget of await widgets.all()) {
      await expect(widget).toHaveAttribute('data-options', expectedOptions);
      await expect(widget).toHaveClass(/lw_widget_11/);
      await expect(widget.locator('a.event-card').first()).toBeVisible();
      await expect(widget.locator('a.event-card h3').first()).not.toHaveText('');
    }

    await expect(page.locator('script#lw_lwcw')).toHaveCount(1);
  });

  test.afterAll(async () => {
    if (pageNid) {
      drush(`du:delete-content --nid="${pageNid}"`);
    }
  });
});
