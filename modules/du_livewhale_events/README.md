# DU LiveWhale Events

The `du_livewhale_events` submodule provides a **DU LiveWhale Events**
Paragraph type for embedding saved LiveWhale event widgets on Drupal pages.

It is independent of the deprecated event import and display submodules in the
`du_events` package. Enabling it does not enable or change those modules.

## Requirements

- Drupal 9 or 10
- The [Paragraphs](https://www.drupal.org/project/paragraphs) module
- The Drupal core Options module
- Access to the DU LiveWhale Calendar and a saved widget ID

## Installation

Enable the submodule directly:

```shell
ddev drush en -y du_livewhale_events
ddev drush cr
```

The module installs:

- the `du_livewhale_events` Paragraph type
- the `field_du_livewhale_widget_id` list field
- the unlimited `field_du_livewhale_groups` string field
- the default Paragraph form and view displays

Installing a Paragraph type does not automatically allow it in every
Paragraph-reference field. If the host field limits allowed Paragraph types,
edit that field on the appropriate content type and enable **DU LiveWhale
Events**.

## Authoring a widget

1. Add a **DU LiveWhale Events** Paragraph to a page.
2. Select a display type. The initial draft maps **Two-column grid** to saved
   LiveWhale widget ID `11`.
3. Optionally add one exact LiveWhale group name per **Groups** item.
4. Save and view the page as an anonymous visitor.

For example, these group values:

```text
Lamont School of Music & Theatre
Newman Center
```

produce this LiveWhale options value:

```text
id=11&format=html&group=Lamont School of Music %26 Theatre|Newman Center
```

LiveWhale separates multiple values with a pipe (`|`). An ampersand contained
in a group name is URL-encoded as `%26` so it is not mistaken for the start of
another widget option. The module performs this encoding; authors should enter
normal group names and should not add `%26` themselves.

## Adding display types

The display selector is a Drupal `list_integer` field. Its stored integer is
the LiveWhale widget ID, while its label gives authors a semantic display name.

For initial testing, additional mappings can be added at:

**Administration > Structure > Paragraph types > DU LiveWhale Events > Manage
fields > Display type**

Keep configuration changes in sync with the module's
`field.storage.paragraph.field_du_livewhale_widget_id.yml` install configuration
before releasing the package. Configuration in `config/install` is only applied
when the module is first installed; changing that YAML alone does not update an
already-installed site.

## LiveWhale script environment

The initial configuration uses the staging widget script:

```text
https://du-staging.lwcal.com/livewhale/theme/core/scripts/lwcw.js
```

Configure the script URL at:

**Administration > Configuration > Web services > DU LiveWhale Events**

Enter the complete HTTPS URL ending in:

```text
/livewhale/theme/core/scripts/lwcw.js
```

Use the staging hostname while testing and switch the site to the confirmed
production LiveWhale hostname before launch. The setting applies to every
LiveWhale Paragraph on the site; it is intentionally not stored on individual
Paragraphs. Drupal attaches the resulting library once per page, even when
several LiveWhale Paragraphs are present.

The setting is stored as normal Drupal configuration in
`du_livewhale_events.settings`. If multiple deployment environments import the
same configuration, override the value in environment-specific `settings.php`:

```php
$config['du_livewhale_events.settings']['script_url'] =
  'https://events.example.edu/livewhale/theme/core/scripts/lwcw.js';
```

A `settings.php` override takes precedence over the value saved by the form.
While an override is active, the form displays the effective value and disables
editing. Change the override in `settings.php` and rebuild caches when switching
that environment.

If the site uses a Content Security Policy, allow the selected LiveWhale host
in the applicable script and connection directives.

## LiveWhale documentation

- [Displaying Event Data on Your Website](https://support.livewhale.com/calendar-onboarding/widgets-and-api/)
- [LiveWhale Calendar Support](https://support.livewhale.com/)
- [DU staging embed example](https://webapps.du.edu/kent/livewhale/test.html)

The LiveWhale widget injects remote HTML into the page rather than rendering an
iframe. Test the resulting markup with the site's styles, accessibility tools,
cookie/privacy requirements, and anonymous-page caching before production use.

## Testing

The Playwright integration test creates a page with two LiveWhale Paragraphs,
loads it anonymously, and verifies that the configured LiveWhale service loads
and populates both widgets. It intentionally uses the real service so upstream
availability and integration failures remain visible.

```shell
npx playwright test --grep @du_livewhale_events
```

For the automated coverage map, executable manual fallback, evidence guidance,
and failure triage, follow the [LiveWhale UAT](../../docs/livewhale-uat.md).
