<?php

namespace Drupal\du_event_import\Plugin\QueueWorker;

use Drupal\du_event_import\EventImport;
use Drupal\du_queue_alerts\QueueAlertsTrait;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process list of profiles to be imported.
 *
 * @QueueWorker(
 *   id = "du_event_import_queue",
 *   title = @Translation("Event Import Queue"),
 *   cron = {"time" = 30}
 * )
 */
class EventImportQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use QueueAlertsTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Event Import service.
   *
   * @var \Drupal\du_event_import\EventImport
   */
  protected $eventImport;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\du_event_import\EventImport $event_import
   *   The event import service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EventImport $event_import,
    LoggerChannelFactoryInterface $logger
  ) {
    $this->setModule('du_event_import');
    $this->entityTypeManager = $entity_type_manager;
    $this->eventImport = $event_import;
    $this->loggerFactory = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('du_event_import'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($event) {
    $this->addCount();
    $logger = $this->loggerFactory->get('du_event_import');

    $response = $this->eventImport->getEvent($event['id']);

    if ($response) {
      $data = Json::decode((string) $response->getBody());

      if (!empty($data)) {
        $data = reset($data);

        if (!is_array($data)) {
          $logger->error(
            "The event import queue worker skipped over event ID %event_id because it didn't get valid data from the API.",
            ['%event_id' => $event['id']]
          );
          $this->addError(
            "The event import queue worker skipped over event ID %s because it didn't get valid data from the API.",
            $event['id']
          );
          return;
        }

        // Find if the node already exists.
        $node_storage = $this->entityTypeManager->getStorage('node');
        $query = $node_storage->getQuery()
          ->condition('type', 'event')
          ->condition('field_event_id', $event['id']);
        $nids = $query->accessCheck(TRUE)->execute();

        $isNew = FALSE;
        if (!empty($nids)) {
          $node = $node_storage->load(reset($nids));
        }
        else {
          // Check and import event if it has both Audience Type and Event Type.
          $validation_status = $this->hasAudienceEventTypeCheck($data);
          if (!$validation_status) {
            // Validation status = FALSE so skip event from import.
            $logger->error(
              "The event import queue worker skipped over event ID %event_id because it is missing audiences or event type.",
              ['%event_id' => $event['id']]
            );
            $this->addError(
              "The event import queue worker skipped over event ID %s because it is missing audiences or event type.",
              $event['id']
            );
            return;
          }
          else {
            // Create new Event node for import.
            $node = $node_storage->create(['type' => 'event']);
            $isNew = TRUE;
          }
        }

        // Import/update the node if it is new or the hash has changed.
        if ($isNew || $node->get('field_event_api_hash')->value != $this->eventImport->getHash($event)) {
          $this->eventImport->importNode($node, $data);
          $logger->info(
            'The event import queue worker imported event ID: %event_id.',
            ['%event_id' => $event['id']]
          );
        }
        else {
          $logger->info(
            'The event import queue worker skipped over event ID %event_id because it is already imported and nothing changed.',
            ['%event_id' => $event['id']]
          );
        }
      }
      else {
        $logger->error(
          'The event import queue worker was executed and got a %code response from the API, but failed to parse the data for event ID: %event_id.',
          [
            '%code' => $response->getStatusCode(),
            '%event_id' => $event['id'],
          ]
        );
        $this->addError(
          'The event import queue worker was executed and got a %s response from the API, but failed to parse the data for event ID: %s.',
          $response->getStatusCode(),
          $event['id']
        );
      }
    }
    else {
      $logger->error(
        'The event import queue worker was executed but failed to get the event ID %event_id from the API.',
        ['%event_id' => $event['id']]
      );
      $this->addError(
        'The event import queue worker was executed but failed to get the event ID %s from the API.',
        $event['id']
      );
    }
  }

  /**
   * Checks if Event has both Audience Type and Event Type.
   *
   * @param object $event
   *   The event object.
   *
   * @return bool
   *   TRUE if the event has both field values, FALSE otherwise.
   */
  public function hasAudienceEventTypeCheck($event) {

    // Default validation status to false.
    $validation_status = FALSE;

    // Check for Audience Type and Event Type.
    $audiences = $types = [];
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
    }

    if (!empty($audiences) && !empty($types)) {
      // Event has both so set validation status to true.
      $validation_status = TRUE;
    }

    return $validation_status;
  }

}
