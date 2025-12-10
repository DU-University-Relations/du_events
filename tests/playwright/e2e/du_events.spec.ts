import { test, expect } from '@du_pw/test';
import { getRole, logInViaForm, logOutViaUi } from "@du_pw/support/users";
import {drush} from "@du_pw/support/drush";
import {faker} from "@faker-js/faker";

test.describe('@du_events - Tests for the events package', () => {
  const administrator = getRole('administrator');
  const event_title = faker.lorem.words(3);

  // Create a future date for the event (tomorrow at 10am - 11am).
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const startDate = tomorrow.toISOString().split('T')[0] + 'T10:00:00';
  const endDate = tomorrow.toISOString().split('T')[0] + 'T11:00:00';

  let eventNid: string;
  let paragraphId: string;

  test.beforeAll(async () => {
    // Create the time/place paragraph first.
    const paragraph_data = JSON.stringify({
      type: 'event_time_place',
      field_event_time_start_date: startDate,
      field_event_time_end_date: endDate,
      field_event_time_location: 'Test Location',
    });
    paragraphId = drush(`du:create-paragraph --data="${paragraph_data}"`);

    // Create the event node and attach the paragraph.
    const node_data = JSON.stringify({
      type: 'event',
      title: event_title,
      field_event_description: 'Test event description for iCal download testing.',
      field_event_time_place: [paragraphId],
    });
    eventNid = drush(`du:create-node --data="${node_data}"`);
  });

  // @source ...
  test('EV1 - Dummy test placeholder...', async ({page, context}) => {
    // 1. Authenticate as qa_administrator.
    await logInViaForm(page, context, administrator);

    // 2. Navigate to the "Add content" page.
    await page.getByRole('link', { name: 'Content', exact: true }).click();
  });

  /**
   * Test for Calendar::generateDownload() iCal file download functionality.
   *
   * @see Drupal\du_event_display\Calendar\Calendar::generateDownload()
   * @see Drupal\du_event_display\Controller\CalendarController
   */
  test('EV2 - Download iCal file from event', async ({page, context}) => {
    // 1. Navigate directly to the event page.
    await page.goto(`/node/${eventNid}`);

    // 2. Verify the "Add to Calendar" button exists.
    const icalButton = page.locator('a[href*="/ical/"]', { hasText: 'Add to Calendar' });
    await expect(icalButton).toBeVisible();

    // 3. Click the button and capture the download.
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      icalButton.click(),
    ]);

    // 4. Verify the downloaded file has the correct extension.
    const filename = download.suggestedFilename();
    expect(filename).toMatch(/\.ics$/);

    // 5. Read and verify the iCal file contents.
    const filePath = await download.path();
    const fs = require('fs');
    const icalContent = fs.readFileSync(filePath, 'utf-8');

    // Verify required iCal structure.
    expect(icalContent).toContain('BEGIN:VCALENDAR');
    expect(icalContent).toContain('END:VCALENDAR');
    expect(icalContent).toContain('BEGIN:VEVENT');
    expect(icalContent).toContain('END:VEVENT');

    // Verify event details are present.
    expect(icalContent).toContain('SUMMARY:');
    expect(icalContent).toContain('DTSTART:');
    expect(icalContent).toContain('DTEND:');
  });

  test.afterAll(async () => {
    // Delete test content.
    drush(`du:delete-content --nid="${eventNid}"`);
  });
});