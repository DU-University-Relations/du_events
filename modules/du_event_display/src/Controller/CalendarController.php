<?php

namespace Drupal\du_event_display\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\du_event_display\Calendar\Calendar;
use Drupal\du_event_display\Calendar\CalendarEvent;

/**
 * Controller for ical file download.
 */
class CalendarController extends ControllerBase {

  /**
   * Generates an ical file the specified event.
   *
   * @param int $nid
   *   Node id for which to generate a calendar file. Must be a valid node of 'event' content type.
   */
  public function icalDownload($nid) {
    $node = Node::load($nid);

    if (empty($node)) {
      return;
    }
    if ($node->getType() != 'event') {
      return;
    }

    // Shamelessly ripped from:
    // http://blog.pamelafox.org/2013/04/outputting-ical-with-php.html
    $tz = new \DateTimeZone("America/Denver");

    // Get the event URL.
    $eventUrl = Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE])->toString();

    // Get the description and strip HTML tags.
    $description = '';
    if (!empty($node->field_event_description->value)) {
      // Strip HTML tags and decode entities for plain text.
      $description = strip_tags($node->field_event_description->value);
      $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      // Normalize whitespace.
      $description = preg_replace('/\s+/', ' ', $description);
      $description = trim($description);
    }

    $events = [];
    if (!empty($node->field_event_time_place)) {
      foreach ($node->field_event_time_place as $paragraph_ref) {
        $paragraph_id = $paragraph_ref->target_id;
        $time_place = Paragraph::load($paragraph_id);

        $startDate = $time_place->field_event_time_start_date->value;
        $endDate = $time_place->field_event_time_end_date->value;

        // Get the location from the paragraph.
        $location = '';
        if (!empty($time_place->field_event_time_location->value)) {
          $location = $time_place->field_event_time_location->value;
        }

        $eventParameters = [
          'summary' => $node->label(),
          'description' => $description,
          'start' => \DateTime::createFromFormat("Y-m-d\TH:i:s", $startDate, $tz),
          'end' => \DateTime::createFromFormat("Y-m-d\TH:i:s", $endDate, $tz),
          'location' => $location,
          'url' => $eventUrl,
        ];
        $events[] = new CalendarEvent($eventParameters);
      }
    }

    $calendar = new Calendar([
      'events' => $events,
      'title' => $node->label(),
      'author' => "University of Denver",
    ]);
    $calendar->generateDownload();

    die();

  }

}
