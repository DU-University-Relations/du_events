<?php

namespace Drupal\du_livewhale_events;

/**
 * Builds the data-options value used by a LiveWhale widget.
 */
final class LiveWhaleOptions {

  /**
   * Builds a widget options string.
   *
   * LiveWhale uses ampersands between options and pipes between multiple
   * values for one option. Encoding each group separately preserves the pipe
   * separator while protecting ampersands and other reserved characters in a
   * group name.
   *
   * @param mixed $widget_id
   *   The saved LiveWhale widget ID.
   * @param array $groups
   *   LiveWhale group names.
   *
   * @return string
   *   The data-options value, or an empty string for an invalid widget ID.
   */
  public static function build($widget_id, array $groups = []) {
    $valid_id = filter_var($widget_id, FILTER_VALIDATE_INT, [
      'options' => ['min_range' => 1],
    ]);

    if ($valid_id === FALSE) {
      return '';
    }

    $options = [
      'id=' . $valid_id,
      'format=html',
    ];
    $encoded_groups = [];

    foreach ($groups as $group) {
      $group = trim((string) $group);
      if ($group === '') {
        continue;
      }

      // Keep spaces readable like LiveWhale's examples while URL-encoding
      // reserved characters such as ampersands and literal pipes.
      $encoded_groups[] = str_replace('%20', ' ', rawurlencode($group));
    }

    if ($encoded_groups) {
      $options[] = 'group=' . implode('|', $encoded_groups);
    }

    return implode('&', $options);
  }

}
