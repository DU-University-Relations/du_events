import { test, expect } from '@du_pw/test';
import { getRole, logInViaForm, logOutViaUi } from "@du_pw/support/users";

test.describe('@du_events - Tests for the events package', () => {
  const administrator = getRole('administrator');

  // @source ...
  test('EV1 - Add a new event', async ({page, context}) => {
    // 1. Authenticate as qa_administrator.
    await logInViaForm(page, context, administrator);

    // 2. Navigate to the "Add content" page.
    await page.getByRole('link', { name: 'Content', exact: true }).click();
  });
});