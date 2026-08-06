<?php

namespace Drupal\du_livewhale_events;

/**
 * Defines and validates the external LiveWhale widget script URL.
 */
final class LiveWhaleScriptUrl {

  /**
   * The staging script used by a new module installation.
   */
  const DEFAULT_URL = 'https://du-staging.lwcal.com/livewhale/theme/core/scripts/lwcw.js';

  /**
   * The required path for the LiveWhale widget loader.
   */
  const SCRIPT_PATH = '/livewhale/theme/core/scripts/lwcw.js';

  /**
   * Determines whether a value is a supported LiveWhale widget script URL.
   *
   * @param mixed $url
   *   The URL to validate.
   *
   * @return bool
   *   TRUE when the value is an absolute HTTPS URL with the expected path.
   */
  public static function isValid($url) {
    if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
      return FALSE;
    }

    if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
      return FALSE;
    }

    return parse_url($url, PHP_URL_PATH) === self::SCRIPT_PATH;
  }

}
