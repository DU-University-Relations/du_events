import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@du_pw/test';
import { drush } from '@du_pw/support/drush';

const loadingBehaviorScript = readFileSync(
  resolve(
    __dirname,
    '../../../modules/du_livewhale_events/js/du-livewhale-events-loading.js',
  ),
  'utf8',
);

test('LW0 - recognizes widget-agnostic injected content', async ({ page }) => {
  await page.route(
    'https://fixture.example/live/resource/css/widget.css',
    (route) => route.fulfill({
      contentType: 'text/css',
      body: '.classic-events-list { display: block; }',
    }),
  );
  await page.setContent(`
    <script>
      window.Drupal = {
        behaviors: {},
        t: function (message) { return message; }
      };
      window.once = function (id, selector, context) {
        return Array.from(context.querySelectorAll(selector));
      };
    </script>
    <div data-du-livewhale-loading>
      <div class="lwcw">
        <link rel="stylesheet" href="https://fixture.example/live/resource/css/widget.css">
        <script type="application/json">{}</script>
      </div>
      <div class="du-livewhale-events__placeholder">Loading test events…</div>
      <span class="du-livewhale-events__status"></span>
    </div>
  `);
  await page.addScriptTag({ content: loadingBehaviorScript });

  const container = page.locator('[data-du-livewhale-loading]');
  await expect(container).toHaveClass(/du-livewhale-events--loading/);

  await page.locator('.lwcw').evaluate((widget) => {
    const content = document.createElement('div');
    content.className = 'lwc-event-widget classic-events-list';
    content.textContent = 'Classic event';
    widget.append(content);
  });

  await expect(container).toHaveClass(/du-livewhale-events--loaded/);
  await expect(container).not.toHaveClass(/du-livewhale-events--timed-out/);
});

/**
 * Manual fallback and behavior contract:
 * https://github.com/DU-University-Relations/du-playwright/blob/main/docs/uat/qa-037-livewhale-events.md
 */
test.describe('@du_livewhale_events - LiveWhale event embeds', () => {
  test.describe.configure({ mode: 'serial' });

  const expectedOptions =
    'id=11&format=html&group=Lamont School of Music %26 Theatre|Newman Center';
  const renderedWidgetContent =
    ':scope > :not(link):not(script):not(style):not(noscript):not(template)';

  let pageNid = '';
  let originalLoadingPlaceholderEnabled = true;
  let originalMinimumHeight = 320;
  let originalLoadingText = 'Loading events…';

  const setLoadingConfiguration = (
    enabled: boolean,
    minimumHeight: number,
    loadingText: string,
  ) => {
    const encodedLoadingText = Buffer.from(loadingText).toString('base64');

    drush(
      `config:set du_livewhale_events.settings loading_placeholder_enabled ${enabled} --input-format=yaml -y`,
    );
    drush(
      `config:set du_livewhale_events.settings minimum_height ${minimumHeight} --input-format=yaml -y`,
    );
    drush(
      `php:eval '\\Drupal::configFactory()->getEditable("du_livewhale_events.settings")->set("loading_text", base64_decode("${encodedLoadingText}"))->save();'`,
    );
    drush('cache:rebuild');
  };

  test.beforeAll(async () => {
    const originalConfiguration = JSON.parse(
      drush(
        'php:eval \'print json_encode(\\Drupal::configFactory()->getEditable("du_livewhale_events.settings")->getRawData());\'',
      ),
    );
    originalLoadingPlaceholderEnabled =
      originalConfiguration.loading_placeholder_enabled ?? true;
    originalMinimumHeight = originalConfiguration.minimum_height ?? 320;
    originalLoadingText =
      originalConfiguration.loading_text ?? 'Loading events…';
    setLoadingConfiguration(true, 333, 'Loading test events…');

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

  test('LW1 - stabilizes loading and renders events from the real service', async ({ page }) => {
    let releaseWidgetRequests = () => {};
    const heldWidgetRequests = new Promise<void>((resolve) => {
      releaseWidgetRequests = resolve;
    });

    await page.route('**/live/widget/**', async (route) => {
      await heldWidgetRequests;
      await route.continue();
    });

    const loaderResponsePromise = page.waitForResponse((response) =>
      response.url().endsWith('/livewhale/theme/core/scripts/lwcw.js'),
    );

    await page.goto(`/node/${pageNid}`, { waitUntil: 'domcontentloaded' });

    const containers = page.locator('[data-du-livewhale-loading]');
    await expect(containers).toHaveCount(2);

    try {
      for (const container of await containers.all()) {
        await expect(container).toHaveClass(/du-livewhale-events--loading/);
        await expect(container).toHaveAttribute('aria-busy', 'true');
        await expect(
          container.locator('.du-livewhale-events__placeholder'),
        ).toBeVisible();
        await expect(
          container.locator('.du-livewhale-events__placeholder-text'),
        ).toHaveText('Loading test events…');
        await expect(
          container.locator('.du-livewhale-events__status'),
        ).toHaveText('Loading test events…');
        await expect(container).toHaveCSS('min-height', '333px');
      }
    } finally {
      releaseWidgetRequests();
    }

    const loaderResponse = await loaderResponsePromise;
    expect(loaderResponse.ok()).toBe(true);

    const widgets = page.locator('.lwcw');
    await expect(widgets).toHaveCount(2);

    for (const widget of await widgets.all()) {
      await expect(widget).toHaveAttribute('data-options', expectedOptions);
      await expect(widget).toHaveClass(/lw_widget_11/);
      await expect(
        widget.locator(renderedWidgetContent).first(),
      ).toBeVisible();
      await expect(
        widget.locator(renderedWidgetContent).first(),
      ).not.toHaveText('');
    }

    for (const container of await containers.all()) {
      await expect(container).toHaveClass(/du-livewhale-events--loaded/);
      await expect(container).not.toHaveClass(/du-livewhale-events--loading/);
      await expect(container).toHaveAttribute('aria-busy', 'false');
      await expect(
        container.locator('.du-livewhale-events__placeholder'),
      ).toBeHidden();
    }

    await expect(page.locator('script#lw_lwcw')).toHaveCount(1);
  });

  test('LW2 - can disable loading enhancement while retaining reserved height', async ({ page }) => {
    setLoadingConfiguration(false, 333, 'Loading test events…');

    await page.goto(`/node/${pageNid}`);

    const containers = page.locator('.du-livewhale-events');
    await expect(containers).toHaveCount(2);
    await expect(page.locator('[data-du-livewhale-loading]')).toHaveCount(0);
    await expect(
      page.locator('.du-livewhale-events__placeholder'),
    ).toHaveCount(0);

    for (const container of await containers.all()) {
      const widget = container.locator('.lwcw');

      await expect(container).toHaveCSS('min-height', '333px');
      await expect(widget.locator(renderedWidgetContent).first()).toBeVisible();
    }
  });

  test.afterAll(async () => {
    try {
      if (pageNid) {
        drush(`du:delete-content --nid="${pageNid}"`);
      }
    } finally {
      setLoadingConfiguration(
        originalLoadingPlaceholderEnabled,
        originalMinimumHeight,
        originalLoadingText,
      );
    }
  });
});
