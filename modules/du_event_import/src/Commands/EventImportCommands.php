<?php

namespace Drupal\du_event_import\Commands;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\du_event_import\EventImport;
use Drush\Commands\DrushCommands;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * A Drush commandfile for the event importer.
 */
class EventImportCommands extends DrushCommands {

  /**
   * The Event Import service.
   *
   * @var \Drupal\du_event_import\EventImport
   */
  protected $eventImport;

  /**
   * The Queue Factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an EventImportCommands object.
   *
   * @param \Drupal\du_event_import\EventImport $event_import
   *   The event import service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(
    EventImport $event_import,
    QueueFactory $queue_factory,
    LoggerChannelFactoryInterface $logger,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->eventImport = $event_import;
    $this->queueFactory = $queue_factory;
    $this->loggerFactory = $logger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Query the event API and add results to the queue.
   *
   * @command du:event-queue
   *
   * @aliases du-eq,du-event-queue
   */
  public function eventImportQueue() {
    $logger = $this->loggerFactory->get('du_event_import');

    $events = $this->eventImport->getEvents();

    if (!empty($events)) {
      $queue = $this->queueFactory->get('du_event_import_queue');

      foreach ($events as $event) {
        $queue->createItem($event);
      }
      $logger->info(
        'The drush du:event-queue command was executed and %count events were added to the queue.',
        ['%count' => count($events)]
      );
    }
    else {
      $error = 'The drush du:event-queue command was executed and got a response from the API, but failed to parse the data.';
      $logger->error($error);
    }
  }

  /**
   * Query and archive events older than 6 months.
   *
   * @command du:event-archive
   *
   * @aliases du-ea,du-event-archive
   */
  public function eventArchive() {
    $logger = $this->loggerFactory->get('du_event_import');
    $node_storage = $this->entityTypeManager->getStorage('node');

    $cutoff_date = new DrupalDateTime('-6 months');
    $cutoff = $cutoff_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $cutoff_stamp = $cutoff_date->getTimeStamp();

    $query = $node_storage->getQuery()
      ->condition('type', 'event')
      ->condition('status', '1')
      ->condition('field_event_time_place.entity.field_event_time_end_date', $cutoff, '<');
    $nids = $query->accessCheck(TRUE)->execute();

    $events = $node_storage->loadMultiple($nids);

    if (!empty($events)) {
      foreach ($events as $event) {
        $event_archive = TRUE;

        foreach ($event->field_event_time_place as $time) {
          if (isset($time->entity->field_event_time_end_date->date)) {
            $event_end = $time->entity->field_event_time_end_date->date->getTimeStamp();
            if ($event_end > $cutoff_stamp) {
              $event_archive = FALSE;
            }
          }
        }

        if ($event_archive) {
          // Archive event.
          $event->setPublished(FALSE);
          $event->set('moderation_state', 'archived');
          $event->save();

          $logger->info(
            'The %title (%nid) event has been archived.',
            ['%title' => $event->getTitle(), '%nid' => $event->id()]
          );
        }
      }

      $logger->info(
        'The drush du:event-archive command was executed and %num events were processed.',
        ['%num' => count($events)]
      );
    }
    else {
      $logger->info('The drush du:event-archive command was executed but there were no events to archive.');
    }
  }

}
