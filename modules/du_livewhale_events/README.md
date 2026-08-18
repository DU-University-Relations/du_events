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

## Container width and loading behavior

The same settings form also provides an optional **Events container maximum
width**. Enter a positive CSS length such as `1200px` or `75rem` to constrain
and center every LiveWhale events container. Leave it blank to let LiveWhale and
the site's theme control the width. The module applies this only to its stable
container and does not depend on or override LiveWhale's injected markup.

The form controls three independent loading settings:

- **Enable enhanced loading placeholder** reserves the widget area, displays a
  neutral spinner and loading message, and keeps injected markup out of layout
  until LiveWhale's widget content and remote stylesheet are ready. Readiness
  does not depend on markup from a specific LiveWhale display type. The widget
  fails open after 10 seconds so a loading-integration problem cannot leave
  populated events permanently hidden.
- **Reserved minimum height** sets the minimum height of every widget container
  in pixels. The widget can grow beyond this value. Enter `0` to disable space
  reservation without changing the loading-placeholder setting.
- **Loading text** controls the short message displayed beside the spinner and
  announced to assistive technology. It defaults to `Loading events…`.

The spinner is a small CSS component owned by this submodule. Drupal core's
AJAX throbber is bundled with the much larger `core/drupal.ajax` behavior, so it
is not attached for this passive third-party loading state.

For the JavaScript lifecycle, readiness signals, timeout behavior, and
maintenance contracts, see [`js/README.md`](js/README.md).

New and updated installations default to an enabled placeholder and a `320px`
minimum height. The value is a site-wide baseline rather than an exact promise:
LiveWhale content, fonts, and responsive column changes can produce a taller
final container, so the enhancement reduces layout shifts but cannot guarantee
a zero CLS score. Sites should tune it against representative content at
desktop and mobile widths. Site-specific responsive CSS can override the
container's `min-block-size` when one administrative value is insufficient.

Disabling the enhanced placeholder removes its loading-indicator markup and
local JavaScript while preserving the configured minimum height. Setting the
height to `0` and disabling the placeholder restores LiveWhale's default
loading behavior.

The setting is stored as normal Drupal configuration in
`du_livewhale_events.settings`. If multiple deployment environments import the
same configuration, override the value in environment-specific `settings.php`:

```php
$config['du_livewhale_events.settings']['script_url'] =
  'https://events.example.edu/livewhale/theme/core/scripts/lwcw.js';
```

A `settings.php` override takes precedence over the value saved by the form.
While a script URL override is active, the form displays the effective value
and disables only that field. The loading controls remain editable. Change the
override in `settings.php` and rebuild caches when switching that environment.

Existing installations receive the loading defaults through
`du_livewhale_events_update_10001()` and the disabled-by-default container width
through `du_livewhale_events_update_10002()`. Run database updates and rebuild
caches after deploying these changes:

```shell
ddev drush updb -y
ddev drush cr
```

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

Centralized Playwright coverage uses a published Newman Center fixture and the
real LiveWhale service. It verifies that the Drupal page responds successfully
and that LiveWhale renders more than one visible event item. Editorial setup,
loader diagnostics, loading behavior, and graceful degradation remain in the
manual UAT contract.

```shell
SITE=du-newmancenter SITE_ENV=livewhale \
  npx playwright test tests/sites/du-newmancenter/livewhale.spec.ts \
  --grep @qa-037
```

For the automated coverage map, executable manual fallback, evidence guidance,
and failure triage, follow the
[QA-037 LiveWhale UAT](https://github.com/DU-University-Relations/du-playwright/blob/main/docs/uat/qa-037-livewhale-events.md)
in the centralized QA repository.
