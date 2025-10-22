<?php

namespace Drupal\du_event_import\Form;

use Drupal\du_event_import\EventImport;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main du_event_import module configuration form.
 */
class ModuleConfigurationForm extends ConfigFormBase {

  /**
   * The Event Import service.
   *
   * @var \Drupal\du_event_import\EventImport
   */
  protected $eventImport;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Queue Factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Construct the configuration form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\du_event_import\EventImport $event_import
   *   The event import service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EventImport $event_import, ModuleHandlerInterface $module_handler, QueueFactory $queue_factory, MessengerInterface $messenger) {
    parent::__construct($config_factory);
    $this->eventImport = $event_import;
    $this->moduleHandler = $module_handler;
    $this->queueFactory = $queue_factory;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('du_event_import'),
      $container->get('module_handler'),
      $container->get('queue'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'du_event_import_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'du_event_import.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('du_event_import.settings');

    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Location'),
      '#description' => $this->t('URL to Events Endpoint'),
      '#default_value' => $config->get('api_url'),
    ];
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
    ];
    $form['client_secret'] = [
      '#type' => 'item',
      '#title' => $this->t('Client Secret'),
      '#markup' => $this->t('Using Key: <code>du_event_import_key</code>'),
      '#description' => $this->t('This value is only editable via the Terminus Secrets Manager Plugin'),
    ];
    $form['calendars'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Calendar(s)'),
      '#description' => $this->t('Separate multiple Web Calendar IDs with commas (no whitespace)'),
      '#default_value' => $config->get('calendars'),
    ];
    $form['search_window'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Window'),
      '#description' => $this->t('Enter how far ahead to get events from today. Example: "+3 months" or "+40 days".'),
      '#default_value' => $config->get('search_window'),
    ];

    $form['import'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Manual Import'),
    ];
    $form['import']['custom_json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom JSON'),
      '#description' => $this->t('When manually executing the import process, you may use this to supply custom data instead of pulling what the API offers. Ignores paging and item count limits, but follow import count limit.'),
      '#default_value' => '',
    ];
    $form['import']['submit_execute'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute Manual Import'),
      '#du_button_id' => 'execute_import',
    ];

    if ($this->moduleHandler->moduleExists('devel') && $this->moduleHandler->moduleExists('kint')) {
      $form['test'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Test API'),
      ];
      $form['test']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Test the API'),
        '#du_button_id' => 'test_api',
      ];

      if ($form_state->getTemporaryValue('test_api') == TRUE) {
        $test_data = ['status' => 'failed'];
        $events = $this->eventImport->getEvents();

        if (!empty($events)) {
          $test_data = [
            'item_count' => count($events),
            'items' => $events,
          ];
        }
        kint($test_data);
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $trigger = $form_state->getTriggeringElement();

    // Running a test. Set a temporary value so that we can query the API when
    // the form reloads and output results using kint().
    if (isset($trigger['#du_button_id']) && $trigger['#du_button_id'] === 'test_api') {
      $form_state->setTemporaryValue('test_api', TRUE);
      $form_state->setRebuild(TRUE);
    }

    // Importing custom JSON data (add to the queue).
    if (isset($trigger['#du_button_id']) && $trigger['#du_button_id'] === 'execute_import') {
      $data = Json::decode((string) $values['custom_json']);

      if (!empty($data)) {
        $queue = $this->queueFactory->get('du_event_import_queue');

        // Check if there is an array of events or just a single event.
        $count = 0;
        if (count($data) > 1 && empty($data['id'])) {
          foreach ($data as $event) {
            if (!empty($event['id'])) {
              $queue->createItem($event);
              $count++;
            }
          }
        }
        else {
          if (!empty($data['id'])) {
            $queue->createItem($data);
            $count++;
          }
        }
      }

      if ($count > 0) {
        $this->messenger->addMessage($this->t('%count events were added to the queue.', ['%count' => $count]));
      }
      else {
        $this->messenger->addWarning($this->t('No events were added. Check that the JSON is valid.'));
      }
    }

    $this->config('du_event_import.settings')
      ->set('api_url', $values['api_url'])
      ->set('client_id', $values['client_id'])
      // ->set('client_secret', $values['client_secret'])
      ->set('calendars', $values['calendars'])
      ->set('search_window', $values['search_window'])
      ->save();
  }

}
