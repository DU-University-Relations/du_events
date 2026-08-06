<?php

namespace Drupal\du_livewhale_events\Form;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\du_livewhale_events\LiveWhaleScriptUrl;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures the LiveWhale widget script used by the site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * Constructs the settings form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   The library discovery service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LibraryDiscoveryInterface $library_discovery) {
    parent::__construct($config_factory);
    $this->libraryDiscovery = $library_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('library.discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'du_livewhale_events_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'du_livewhale_events.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config_name = 'du_livewhale_events.settings';
    $stored_config = $this->config($config_name);
    $effective_config = $this->configFactory()->get($config_name);
    $is_overridden = $effective_config->hasOverrides('script_url');

    $form['script_url'] = [
      '#type' => 'url',
      '#title' => $this->t('LiveWhale widget script URL'),
      '#description' => $this->t('Enter the complete HTTPS URL to the LiveWhale lwcw.js script for this site. Use the staging host while testing and the live host in production.'),
      '#default_value' => $effective_config->get('script_url') ?: $stored_config->get('script_url') ?: LiveWhaleScriptUrl::DEFAULT_URL,
      '#required' => TRUE,
    ];

    $form = parent::buildForm($form, $form_state);

    if ($is_overridden) {
      $form['script_url']['#disabled'] = TRUE;
      $form['script_url']['#description'] = $this->t('This effective value is overridden outside Drupal configuration, typically in settings.php. Change the override and rebuild caches to switch environments.');
      $form['actions']['submit']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $script_url = trim($form_state->getValue('script_url'));

    if (!LiveWhaleScriptUrl::isValid($script_url)) {
      $form_state->setErrorByName('script_url', $this->t('Enter an absolute HTTPS URL ending in @path.', [
        '@path' => LiveWhaleScriptUrl::SCRIPT_PATH,
      ]));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('du_livewhale_events.settings')
      ->set('script_url', trim($form_state->getValue('script_url')))
      ->save();

    // Dynamic library definitions are cached, so rebuild them immediately.
    $this->libraryDiscovery->clearCachedDefinitions();
    parent::submitForm($form, $form_state);
  }

}
