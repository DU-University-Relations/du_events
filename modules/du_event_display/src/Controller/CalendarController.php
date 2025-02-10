<?php

namespace Drupal\du_event_display\Controller;

use Drupal\Core\Controller\ControllerBase;
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

    $events = [];
    if (!empty($node->field_event_time_place)) {
      foreach ($node->field_event_time_place as $paragraph_ref) {
        $paragraph_id = $paragraph_ref->target_id;
        $time_place = Paragraph::load($paragraph_id);

        $startDate = $time_place->field_event_time_start_date->value;
        $endDate = $time_place->field_event_time_end_date->value;

        $eventParameters = [
          // 'uid' =>  '123',.
          'summary' => $node->label(),
          'description' => $node->field_event_description,
          'start' => \DateTime::createFromFormat("Y-m-d\TH:i:s", $startDate, $tz),
          'end' => \DateTime::createFromFormat("Y-m-d\TH:i:s", $endDate, $tz),
          'location' => '',
        ];
        $events[] = new CalendarEvent($eventParameters);
      }
    }

    $calendar = new Calendar([
      'events' => $events,
      'title' => "Calendar Title",
      'author' => "Denver University",
    ]);
    $calendar->generateDownload();

    die();

  }

}
