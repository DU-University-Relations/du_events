# DU Event Display

Custom functionality to display event entities and generate iCal calendar downloads for University of Denver sites.

## Features

- **iCal Download**: Generate `.ics` calendar files for events via the "Add to Calendar" button
- **Event List Display**: Preprocess functions for event nodes and event list paragraphs
- **Views Integration**: Date filtering and exposed form alterations for the `event_list` view
- **Site Configuration**: Admin settings for event list page location

## Routes

| Path | Description |
|------|-------------|
| `/ical/{nid}` | Downloads an iCal file for the specified event node |

## Architecture

### Calendar Classes

Located in `src/Calendar/`, these classes generate iCal-compliant output:

- **`Calendar`**: Container for calendar events, generates the `VCALENDAR` wrapper
- **`CalendarEvent`**: Represents a single `VEVENT` with start/end times, summary, description, and location

Based on [pamelafox-coursera's iCal implementation](https://gist.github.com/pamelafox-coursera/5359246).

### Controller

- **`CalendarController::icalDownload($nid)`**: Loads an event node, extracts time/place data from paragraphs, and triggers an iCal file download

### Hooks (du_event_display.module)

| Hook | Purpose |
|------|---------|
| `hook_theme()` | Registers `event_filter_buttons` template |
| `hook_preprocess_node__event()` | Adds iCal button and event list location to event nodes |
| `hook_preprocess_paragraph__event_list()` | Builds filter UI and embeds the event list view |
| `hook_form_views_exposed_form_alter()` | Converts text inputs to date selectors for `event_list` view |
| `hook_views_pre_view()` | Sets default date filters and handles unit taxonomy filtering |
| `hook_preprocess_views_view_fields()` | Formats date/time display for event list items |
| `hook_form_system_site_information_settings_alter()` | Adds event list location setting |

## Testing

### PHPUnit (Unit Tests)

Unit tests for the Calendar classes are in `tests/src/Unit/Calendar/`:

```bash
# Run from drupal-composer-managed root
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/packages/du_events/modules/du_event_display
```

**Test coverage:**
- `CalendarTest`: Constructor defaults, custom title/author, ICS format validation, single/multiple events
- `CalendarEventTest`: UID generation, VEVENT format, date formatting, special character escaping

### Playwright (E2E Tests)

The iCal download functionality is tested via Playwright in the parent `du_events` package:

```bash
# Run from drupal-composer-managed root
npx playwright test --grep @du_events
```

**Test coverage (EV2):**
- Navigate to event page
- Verify "Add to Calendar" button visibility
- Trigger download and validate `.ics` file extension
- Verify iCal content structure (`VCALENDAR`, `VEVENT`, `SUMMARY`, `DTSTART`, `DTEND`)

### Testing Philosophy

| Layer | Tool | Use Case |
|-------|------|----------|
| Logic (isolated) | PHPUnit Unit | `Calendar` and `CalendarEvent` classes |
| HTTP/Download | Playwright | `CalendarController` download behavior |

The `CalendarController` is not unit tested because it:
- Uses static entity loading (`Node::load()`, `Paragraph::load()`)
- Produces HTTP output via `header()` and `die()`
- Is better validated through end-to-end download verification

## Configuration

Event list page location can be configured at:
**Administration > Configuration > System > Site information > Events Page**

This setting controls the "Back to Events" link on individual event pages.
