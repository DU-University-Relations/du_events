<?php

namespace Drupal\du_event_display\Calendar;

/**
 * Third party class (https://gist.github.com/pamelafox-coursera/5359246)
 */
class CalendarEvent {

  /**
   * The event ID.
   *
   * @var string
   */
  private $uid;

  /**
   * The event start date.
   *
   * @var DateTime
   */
  private $start;

  /**
   * The event end date.
   *
   * @var DateTime
   */
  private $end;

  /**
   * The event title.
   *
   * @var string
   */
  private $summary;

  /**
   * The event description.
   *
   * @var string
   */
  private $description;

  /**
   * The event location.
   *
   * @var string
   */
  private $location;

  /**
   * The event URL.
   *
   * @var string
   */
  private $url;

  /**
   * Constructor.
   */
  public function __construct($parameters) {
    $parameters += [
      'summary' => 'Untitled Event',
      'description' => '',
      'location' => '',
      'url' => '',
    ];
    if (isset($parameters['uid'])) {
      $this->uid = $parameters['uid'];
    }
    else {
      $this->uid = uniqid(rand(0, getmypid()));
    }
    $this->start = $parameters['start'];
    $this->end = $parameters['end'];
    $this->summary = $parameters['summary'];
    $this->description = $parameters['description'];
    $this->location = $parameters['location'];
    $this->url = $parameters['url'];
    return $this;
  }

  /**
   * Get the start time set for the even.
   */
  private function formatDate($date) {
    return $date->format("Ymd\THis\Z");
  }

  /**
   * Escape commas, semi-colons, backslashes, and newlines per iCalendar spec.
   *
   * @see http://stackoverflow.com/questions/1590368/should-a-colon-character-be-escaped-in-text-values-in-icalendar-rfc2445
   *
   * @param string|object $str
   *   A string, an object with a 'value' property (like Drupal field items),
   *   or a Drupal FieldItemList.
   *
   * @return string
   *   The escaped string.
   */
  private function formatValue($str) {
    // Handle empty/null values.
    if (empty($str)) {
      return '';
    }

    // Handle plain strings.
    if (is_string($str)) {
      $value = $str;
    }
    // Handle Drupal FieldItemList objects (e.g., $node->field_name).
    elseif (is_object($str) && method_exists($str, 'isEmpty')) {
      if ($str->isEmpty()) {
        return '';
      }
      // Get the first item's value.
      $value = $str->first()->value ?? '';
    }
    // Handle objects with a 'value' property (like individual field items).
    elseif (is_object($str) && property_exists($str, 'value')) {
      $value = $str->value;
    }
    else {
      $value = '';
    }

    // Escape backslashes first, then other special characters.
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace(',', '\\,', $value);
    $value = str_replace(';', '\\;', $value);
    // Newlines must be escaped as \n in iCalendar.
    $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
    return $value;
  }

  /**
   * Generate the full string to return.
   */
  public function generateString() {
    $created = new \DateTime();

    $content = "BEGIN:VEVENT\r\n";
    $content .= "UID:{$this->uid}\r\n";
    $content .= "DTSTART:{$this->formatDate($this->start)}\r\n";
    $content .= "DTEND:{$this->formatDate($this->end)}\r\n";
    $content .= "DTSTAMP:{$this->formatDate($this->start)}\r\n";
    $content .= "CREATED:{$this->formatDate($created)}\r\n";
    $content .= "DESCRIPTION:{$this->formatValue($this->description)}\r\n";
    $content .= "LAST-MODIFIED:{$this->formatDate($this->start)}\r\n";
    $content .= "LOCATION:{$this->formatValue($this->location)}\r\n";
    $content .= "SUMMARY:{$this->formatValue($this->summary)}\r\n";
    if (!empty($this->url)) {
      $content .= "URL:{$this->url}\r\n";
    }
    $content .= "SEQUENCE:0\r\n";
    $content .= "STATUS:CONFIRMED\r\n";
    $content .= "TRANSP:OPAQUE\r\n";
    $content .= "END:VEVENT\r\n";

    return $content;
  }

}
