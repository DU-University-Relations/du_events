# DU Events

This is the DU Events repository providing Drupal sites the ability to add and display events 
coming from...

## Main Documentation

The main documentation for this package can be found at:

- [DU Events Confluence Page](https://ducloudwiki.atlassian.net/wiki/x/DIBBIg)

## Local Setup

You will need to have a local copy of the DU profile set up and running first.

- [Install DU Profile Locally](https://ducloudwiki.atlassian.net/wiki/x/F4DDRQ)

Once you have the profile running, you can install this module locally:

```shell
# Go to the packages directory
cd web/modules/packages

# Clone the module
git clone git@github.com:DU-University-Relations/du_events.git
cd du_events

# Enable package
ddev drush en -y du_events
```

## Testing

This module uses the Playwright E2E testing infrastructure from the DU profile
(drupal-composer-managed). The module repository keeps only module-specific test specs in
`tests/playwright/e2e` and optional data in `tests/playwright/fixtures/`.

Quick start:
- [Install Testing on a Package](https://ducloudwiki.atlassian.net/wiki/x/F4DDRQ) has
  instructions on how to install the testing infrastructure on a package.

### Playwright Tests

See [the testing README document](tests/playwright/README.md) for more information on writing
and running tests.

## PHPUnit Tests

The unit tests are located in the submodules, as the main `du_events` module does not contain
any PHP logic to test. 

```shell
# Run unit tests.
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/packages/du_events
```

## Dependencies

There are three submodules that this package installs:

- `du_event_display` - 
- `du_event_import` - 
- `du_event_mini` -

