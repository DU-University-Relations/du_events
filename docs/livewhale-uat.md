# UAT - LiveWhale Event Embeds

**Automation Tag:** `@du_livewhale_events`  
**Last Updated:** 2026-08-06  
**Owner:** Drupal QA / WebOps

## Purpose

Validate that an editor can add DU LiveWhale Events Paragraphs and that an
anonymous visitor receives populated event cards from the configured LiveWhale
service. This document is both the behavior contract for the integration and
the manual fallback when Playwright cannot run.

The test deliberately uses the real LiveWhale service. A service outage,
timeout, invalid response, or Content Security Policy block is a test failure
to investigate and record; it should not be hidden with mocked responses.

## Automated Test Coverage

**Status:** Partially automated

The Playwright test creates two LiveWhale Paragraphs and a Page through the
functional-testing Drush commands, then visits the page anonymously. It proves:

- both widgets receive the expected encoded `data-options` value;
- the configured `lwcw.js` loader responds successfully;
- both widgets display real event cards with nonempty titles; and
- Drupal attaches only one `script#lw_lwcw` loader to the page.

The test does not use the editorial form, inspect every LiveWhale network
request, or exercise the blocked-script behavior. Those checks remain manual.

| Status | Spec File | Test Title | Tag | Covers | Notes |
|---|---|---|---|---|---|
| Automated | [tests/playwright/e2e/du_livewhale_events.spec.ts](../tests/playwright/e2e/du_livewhale_events.spec.ts) | `LW1 - renders events from the configured LiveWhale service` | `@du_livewhale_events` | TC-002, part of TC-003 | Uses real LiveWhale responses and deletes its generated Page afterward. |
| Manual | N/A | N/A | N/A | TC-001 | The automated fixture bypasses the editorial UI. |
| Manual | N/A | N/A | N/A | Remaining TC-003 checks, TC-004 | Requires browser developer tools and request blocking. |

Run the automated test from the host DU profile root:

```shell
ddev drush en -y du_livewhale_events du_functional_testing
npx playwright test web/modules/packages/du_events/tests/playwright/e2e/du_livewhale_events.spec.ts
```

The shorter tag-based command is:

```shell
npx playwright test --grep @du_livewhale_events
```

If the test fails on an external request, record the time, request URL, HTTP
status or browser error, and test trace before retrying. A later successful
retry is recovery evidence, not a replacement for the original failure.

## Configuration Under Test

The site-wide loader URL is configured at:

`Administration > Configuration > Web services > DU LiveWhale Events`

The URL must use HTTPS and end with:

```text
/livewhale/theme/core/scripts/lwcw.js
```

New installations default to:

```text
https://du-staging.lwcal.com/livewhale/theme/core/scripts/lwcw.js
```

An environment-specific `settings.php` override takes precedence over stored
Drupal configuration. When an override is active, the settings form displays
the effective value and disables editing. The selected LiveWhale hostname must
also be allowed by the site's Content Security Policy.

## Test Environment and Prerequisites

**Required Access:**

- A content-editor role that can create and publish a Page containing
  Paragraphs
- Anonymous access to the published test page
- Browser developer tools for TC-003 and TC-004

**Prerequisites:**

- [ ] `du_livewhale_events` is enabled
- [ ] The Page Content field allows the DU LiveWhale Events Paragraph type
- [ ] Saved LiveWhale widget ID `11` exists in the configured LiveWhale
  environment
- [ ] The configured LiveWhale hostname is allowed by Content Security Policy
- [ ] The tester can safely create and delete a temporary Page
- [ ] The target environment and LiveWhale service are reachable

**Test Data:**

| Parameter | Value |
|---|---|
| Display type | `Two-column grid` (widget ID `11`) |
| First group | `Lamont School of Music & Theatre` |
| Second group | `Newman Center` |
| Paragraph count | `2` |
| Expected options | `id=11&format=html&group=Lamont School of Music %26 Theatre\|Newman Center` |

Group names must be entered as normal text. Do not enter `%26` manually.

## Test Cases

### TC-001 - Create a Page with two LiveWhale widgets

**User Role:** Content editor

**Test Scenario:** Confirm that an editor can configure and save two LiveWhale
Paragraphs through the normal Page form.

**Steps:**

1. Confirm the effective loader URL under `Administration > Configuration > Web
   services > DU LiveWhale Events`. Do not change it unless switching the
   environment is part of the test.
2. Create a new Page with a unique title such as `LiveWhale UAT YYYY-MM-DD`.
3. In Page Content, add a DU LiveWhale Events Paragraph.
4. Select `Two-column grid` as the display type.
5. Add `Lamont School of Music & Theatre` and `Newman Center` as two separate
   Groups values.
6. Add a second DU LiveWhale Events Paragraph with the same values.
7. Publish the Page and copy its public URL.

**Expected Result:**

The Page saves successfully and is available at a public URL. Both DU
LiveWhale Events Paragraphs remain present with the selected display type and
two group values when the Page is reopened for editing.

**Notes:**

- Playwright creates equivalent entities through Drush, so the editorial form
  portion of this case is intentionally manual.

### TC-002 - Anonymous visitors receive real event cards

**User Role:** Anonymous visitor

**Test Scenario:** Confirm that both saved widgets are populated by the real
LiveWhale service.

**Steps:**

1. Open a private browser window or log out of Drupal.
2. Navigate to the public Page created in TC-001.
3. Locate both LiveWhale event sections.
4. Confirm each section displays at least one event card with a nonempty event
   title.
5. Open an event link from one section and confirm it reaches a LiveWhale event
   detail page rather than an error page.
6. Return to the Drupal Page and confirm both event sections remain populated.

**Expected Result:**

The Drupal Page loads without an application error. Both LiveWhale Paragraphs
display event cards, the cards have visible titles, and an event link opens a
valid LiveWhale event detail page.

**Notes:**

- Event titles, dates, ordering, and total results may change as LiveWhale
  content changes. The contract is populated, usable event results rather than
  a permanently fixed event title.
- No-results markup is not sufficient for this test data. The selected widget
  and groups are expected to return event cards.

### TC-003 - Options, requests, and loader de-duplication are correct

**User Role:** Anonymous visitor with browser developer tools

**Test Scenario:** Confirm the browser receives the encoded options, loads the
real service successfully, and receives only one loader for two widgets.

**Steps:**

1. Open developer tools on the Page from TC-001 and select the Network panel.
2. Enable `Disable cache` while developer tools are open, then reload the Page.
3. Filter requests for `lwcw.js` and `/live/widget/`.
4. Confirm the loader and widget requests use the configured LiveWhale host and
   complete successfully.
5. In the browser Console, run:

   ```javascript
   [...document.querySelectorAll('.lwcw')]
     .map((element) => element.getAttribute('data-options'))
   ```

6. Confirm the console returns two copies of:

   ```text
   id=11&format=html&group=Lamont School of Music %26 Theatre|Newman Center
   ```

7. In the Console, run:

   ```javascript
   document.querySelectorAll('script#lw_lwcw').length
   ```

8. Review the Console for uncaught LiveWhale, Content Security Policy, or
   cross-origin errors.

**Expected Result:**

There is one successful `lwcw.js` loader request, successful widget requests
for both Paragraphs, two correctly encoded option values, and exactly one
`script#lw_lwcw` element. The browser Console contains no integration error
that prevents either widget from rendering.

**Notes:**

- Additional LiveWhale JavaScript, stylesheet, image, and metadata requests are
  expected.
- HTML source may represent option separators as `&amp;`. The DOM value returned
  by `getAttribute()` should contain normal `&` separators while preserving the
  group-name encoding `%26`.

### TC-004 - The Drupal Page remains usable when LiveWhale is unavailable

**User Role:** Anonymous visitor with browser developer tools

**Test Scenario:** Confirm an external loader failure does not cause a Drupal
application failure, and confirm the widgets recover when the request is
restored.

**Steps:**

1. In browser developer tools, add a Network Request Blocking pattern for
   `*lwcw.js*`.
2. Reload the Page from TC-001.
3. Confirm the LiveWhale event cards do not load and the blocked request is
   visible in developer tools.
4. Confirm the rest of the Drupal Page, including its title, navigation, and
   footer, remains usable.
5. Remove the blocking pattern and reload the Page.
6. Confirm both LiveWhale event sections populate again.

**Expected Result:**

While the loader is blocked, LiveWhale content is unavailable but Drupal does
not display a PHP exception, generic error page, or broken page shell. After
request blocking is removed, both event sections render again without changing
Drupal content or configuration.

## Evidence and Failure Triage

Record the environment, deployed Git SHA, configured loader URL, browser,
timestamp with timezone, and result for each executed test case.

For a failure, capture the Page URL, screenshot, Console error, failed request
URL, HTTP status or browser failure reason, and relevant Drupal log entry. Use
these signals to identify the likely owner:

| Evidence | Likely area to investigate |
|---|---|
| No `script#lw_lwcw` in the DOM | Paragraph rendering, library attachment, or Drupal caches |
| Loader blocked by Content Security Policy | Site CSP configuration for the selected LiveWhale host |
| `lwcw.js` fails or times out | LiveWhale availability, DNS, network, or configured host |
| Loader succeeds but `/live/widget/` fails | LiveWhale widget service, CORS, or saved widget configuration |
| Requests succeed but no event cards appear | Widget ID, group names, LiveWhale content, or theme markup |
| One loader and only one of two widgets populates | Widget options or a client-side LiveWhale error |

Do not repeatedly rerun an external-service failure without first recording
evidence. If a later retry passes, record both the failure and recovery times.

## Cleanup

1. Remove any Network Request Blocking rule added for TC-004.
2. Delete the temporary Page created in TC-001.
3. Restore the original loader URL only if it was intentionally changed for
   this UAT.
4. Do not modify or delete saved widgets, groups, or events in LiveWhale.

## Expected Behaviors That Are Not Bugs

- Event content and ordering may change between runs.
- LiveWhale injects remote HTML into the Drupal Page; it does not render in an
  iframe.
- The settings form is read-only when `settings.php` overrides the loader URL.
- Event content being absent while LiveWhale is blocked or unavailable is an
  external dependency failure; the Drupal Page shell should still render.

## Related Documentation

- [DU LiveWhale Events module documentation](../modules/du_livewhale_events/README.md)
- [Package Playwright instructions](../tests/playwright/README.md)
- [LiveWhale automated test](../tests/playwright/e2e/du_livewhale_events.spec.ts)
- [LiveWhale widget and API documentation](https://support.livewhale.com/calendar-onboarding/widgets-and-api/)
- [DU staging embed example](https://webapps.du.edu/kent/livewhale/test.html)
