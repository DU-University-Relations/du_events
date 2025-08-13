<?php

namespace Drupal\du_event_import;

use Drupal\du_queue_alerts\QueueAlertsTrait;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Event Import service class.
 */
class EventImport {

  use QueueAlertsTrait;

  /**
   * An ACME Services - Contents HTTP Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs an EventImport object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client that can perform remote requests.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger) {
    $this->setModule('du_event_import');
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger;
  }

  /**
   * Query the event API for a single event.
   *
   * @param int $event_id
   *   The event ID.
   *
   * @return mixed
   *   Return the response object, or FALSE if there was an issue.
   */
  public function getEvent(int $event_id) {
    $logger = $this->loggerFactory->get('du_event_import');
    $config = $this->configFactory->get('du_event_import.settings');

    // Config variables.
    $url = $config->get('api_url');
    $client_id = $config->get('client_id');
    $client_secret = $config->get('client_secret');

    // Check for API URL before proceeding.
    if (empty($url)) {
      // Log error.
      $logger->error(
        'The event importer was executed but lacks an API URL. Go to %path to configure.',
        [
          '%path' => '/admin/config/content/du_event_importer',
        ]
      );
      return FALSE;
    }

    // Add credentials if they are set.
    if (!empty($client_id) && !empty($client_secret)) {
      $url .= '/' . $event_id;
      $url .= '?client_id=' . $client_id;
      $url .= '&client_secret=' . $client_secret;
      $url .= '&public=true';

      try {
        $response = $this->httpClient->get($url, ['headers' => ['Accept' => 'application/json']]);
        if ($response->getStatusCode() != 200) {
          throw new \Exception(getStatusCode());
        }
        return $response;
      }
      catch (\Exception $e) {
        // Log error.
        $logger->error(
          'An attempt to import an event from @url failed with a @status status code.',
          [
            '@url' => $url,
            '@status' => $e->getCode(),
          ]
        );
        $this->addError(
          'An attempt to import an event from %s failed with a %s status code.',
          $url,
          $e->getCode()
        );
      }
    }

    return FALSE;
  }

  /**
   * Query the event API for events.
   *
   * @return array
   *   Return array of events.
   */
  public function getEvents() {
    $events = [];

    $logger = $this->loggerFactory->get('du_event_import');
    $config = $this->configFactory->get('du_event_import.settings');

    // Config variables.
    $url = $config->get('api_url');
    $client_id = $config->get('client_id');
    $client_secret = $config->get('client_secret');
    $search_window = $config->get('search_window');

    // Check for API URL before proceeding.
    if (empty($url)) {
      // Log error.
      $logger->error(
        'The event importer was executed but lacks an API URL. Go to %path to configure.',
        [
          '%path' => '/admin/config/content/du_event_importer',
        ]
      );
      return FALSE;
    }

    // Add credentials if they are set.
    if (!empty($client_id) && !empty($client_secret)) {
      // Get start and end dates.
      $start_date = new DrupalDateTime();
      $end_date = new DrupalDateTime($search_window);
      if (empty($end_date)) {
        $end_date = new DrupalDateTime('+3 months');
      }
      $end_timestamp = $end_date->getTimestamp();

      while ($start_date->getTimestamp() < $end_timestamp) {
        $query = http_build_query([
          'client_id' => $client_id,
          'client_secret' => $client_secret,
          'start_date' => $start_date->format('Y-m-d'),
          'public' => 'true',
        ]);

        try {
          $response = $this->httpClient->get(
            $url . '?' . $query,
            ['headers' => ['Accept' => 'application/json']]
          );
          if ($response->getStatusCode() != 200) {
            throw new \Exception(getStatusCode());
          }
          $new_events = Json::decode((string) $response->getBody());
          if (!empty($new_events)) {
            $events = array_column($events, NULL, "id");
            $new_events = array_column($new_events, NULL, "id");
            ksort($events);
            ksort($new_events);
            $events = array_map("unserialize", array_unique(array_map("serialize", array_merge($events, $new_events))));
          }
        }
        catch (\Exception $e) {
          // Log error.
          $logger->error(
            'An attempt to import events from @url failed with a @status status code.',
            [
              '@url' => $url . '?' . $query,
              '@status' => $e->getCode(),
            ]
          );
          $this->addError(
            'An attempt to import events from %s failed with a %s status code.',
            $url . '?' . $query,
            $e->getCode()
          );
        }

        // Query the API in 7 day intervals.
        $start_date->add(new \DateInterval('P7D'));
      }
    }

    return $events;
  }

  /**
   * Import data from the API to an event node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The event node object.
   * @param array $event
   *   Array of event data from the API.
   */
  public function importNode(Node $node, array $event) {
    $title = '';
    if (!empty($event['title'])) {
      $title = html_entity_decode($event['title']);
    }
    else {
      // Skip this event if title is null.
      return;
    }

    $eventHash = $this->getHash($event);

    // Get event audiences and types.
    $audiences = $types = [];
    $audience_terms_array = $type_terms_array = [];
    if (!empty($event['audiences'])) {
      foreach ($event['audiences'] as $audience) {
        $audience = explode(' - ', $audience);
        if ($audience[0] == 'Type') {
          $types[] = $audience[1];
        }
        elseif ($audience[0] == 'Audience') {
          $audiences[] = $audience[1];
        }
      }

      if (!empty($audiences)) {
        $audience_terms = \Drupal::entityQuery('taxonomy_term')
          ->accessCheck(TRUE)
          ->condition('vid', 'event_audiences')
          ->condition('field_event_api_tag', $audiences, 'IN')
          ->execute();
        if (!empty($audience_terms)) {
          foreach ($audience_terms as $key => $term) {
            $audience_terms_array[] = ['target_id' => $key];
          }
        }
      }

      if (!empty($types)) {
        $type_terms = \Drupal::entityQuery('taxonomy_term')
          ->accessCheck(TRUE)
          ->condition('vid', 'event_types')
          ->condition('field_event_api_tag', $types, 'IN')
          ->execute();
        if (!empty($type_terms)) {
          foreach ($type_terms as $key => $term) {
            $type_terms_array[] = ['target_id' => $key];
          }
        }
      }
    }

    $primaryOrg = '';
    $additional_orgs = [];
    $orgs = [];
    if (!empty($event['primaryOrg'][0]['organizationID'])) {
      $orgs[] = $event['primaryOrg'][0]['organizationID'];
      $primaryOrg = $event['primaryOrg'][0]['organizationName'];
    }
    if (!empty($event['secondaryOrgs'])) {
      foreach ($event['secondaryOrgs'] as $org) {
        $orgs[] = $org['organizationID'];
        $additional_orgs[] = ['value' => $org['organizationName']];
      }
    }

    // Match up org IDs with the unit taxonomy term.
    $unit_ids = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(TRUE)
      ->condition('vid', 'unit')
      ->condition('field_25_live_id', $orgs, 'IN')
      ->execute();

    $description = '';
    if (!empty($event['description'])) {
      $description = $event['description'];
    }

    $imageUrl = '';
    if (!empty($event['imageUrl'])) {
      $imageUrl = $event['imageUrl'];
    }

    $repeats = FALSE;
    if (!empty($event['repeats'])) {
      $repeats = ($event['repeats'] == 'true');
    }

    $node->set('field_event_id', $event['id']);
    $node->set('title', $title);
    $node->set('field_event_additional_orgs', $additional_orgs);
    $node->set('field_event_primary_org', ['value' => $primaryOrg]);
    $node->set('field_event_description', ['value' => $description, 'format' => 'rich_text']);
    $node->set('field_event_image', ['value' => $imageUrl]);
    $node->set('field_event_api_hash', $eventHash);
    $node->set('field_event_repeats', $repeats);
    $node->set('field_event_unit', $unit_ids);
    $node->set('field_event_audience', $audience_terms_array);
    $node->set('field_event_type', $type_terms_array);

    // Add event time paragraph references.
    $times = [];
    if (!empty($event['eventTimes'])) {
      $accountSwitcher = \Drupal::service('account_switcher');
      $account = User::load(1);
      $accountSwitcher->switchTo($account);
      foreach ($event['eventTimes'] as $time) {
        $start = '';
        if (!empty($time['startDate'])) {
          $start = DrupalDateTime::createFromFormat('Y-m-d H:i:s', $time['startDate']);
          $start->setTimezone(new \DateTimeZone('UTC'));
        }

        $end = '';
        if (!empty($time['endDate'])) {
          $end = DrupalDateTime::createFromFormat('Y-m-d H:i:s', $time['endDate']);
          $end->setTimezone(new \DateTimeZone('UTC'));
        }

        $location = '';
        if (!empty($time['location'])) {
          $location = html_entity_decode($time['location']);
        }

        $allDay = FALSE;
        if (!empty($time['allDay'])) {
          $allDay = ($time['allDay'] == 'true');
        }

        $timeplace = Paragraph::create(['type' => 'event_time_place']);
        $timeplace->set('field_event_time_start_date', $start->format('Y-m-d\TH:i:s'));
        $timeplace->set('field_event_time_end_date', $end->format('Y-m-d\TH:i:s'));
        $timeplace->set('field_event_time_location', $location);
        $timeplace->set('field_event_time_all_day', $allDay);
        $timeplace->save();

        $times[] = [
          'target_id' => $timeplace->id(),
          'target_revision_id' => $timeplace->getRevisionId(),
        ];
      }
      $accountSwitcher->switchBack();

      // Note: so far, times seem to be in reverse chronological order. So we
      // array_reverse them. We may need to sort, if this doesn't cut it.
      $node->field_event_time_place = array_reverse($times);
    }

    // Publish or unpublish based on state.
    if ($event['stateName'] == 'Confirmed') {
      $node->setPublished(TRUE);
      $node->set('moderation_state', 'published');
    }
    else {
      $node->setPublished(FALSE);
      $node->set('moderation_state', 'archived');
    }

    $node->save();
  }

  /**
   * Generates a hash of an event from the API.
   *
   * This hash will stored in a field on the corresponding node, and will later
   * be used to compare the node to the event and determine whether it has
   * changed and needs to update.
   *
   * @param object $event
   *   Event object as parsed from DU JSON feed with json_decode.
   */
  public function getHash($event) {
    return md5(serialize($event));
  }

}
