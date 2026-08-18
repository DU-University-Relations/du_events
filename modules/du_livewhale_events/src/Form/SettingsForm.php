<?php

namespace Drupal\du_livewhale_events\Form;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
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
    $script_url_is_overridden = $effective_config->hasOverrides('script_url');

    $form['script_url'] = [
      '#type' => 'url',
      '#title' => $this->t('LiveWhale widget script URL'),
      '#description' => $this->t('Enter the complete HTTPS URL to the LiveWhale lwcw.js script for this site. Use the staging host while testing and the live host in production.'),
      '#default_value' => $effective_config->get('script_url') ?: $stored_config->get('script_url') ?: \DU_LIVEWHALE_EVENTS_DEFAULT_SCRIPT_URL,
      '#required' => TRUE,
    ];

    $form['loading_placeholder_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable enhanced loading placeholder'),
      '#description' => $this->t("Reserve the configured space, show a loading indicator, and wait for populated, styled LiveWhale markup before revealing the widget. Disable this to use LiveWhale's default loading behavior."),
      '#default_value' => $effective_config->get('loading_placeholder_enabled') ?? TRUE,
    ];

    $form['minimum_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Reserved minimum height'),
      '#description' => $this->t('Minimum widget-container height in pixels. Adjust this to approximate the final LiveWhale layout and reduce page movement. The widget can grow beyond this value. Enter 0 to disable reserved space.'),
      '#default_value' => $effective_config->get('minimum_height') ?? 320,
      '#min' => 0,
      '#max' => 2000,
      '#step' => 1,
      '#field_suffix' => $this->t('pixels'),
      '#required' => TRUE,
    ];

    $form['loading_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Loading text'),
      '#description' => $this->t('Short text displayed beside the loading indicator and announced to assistive technology.'),
      '#default_value' => $effective_config->get('loading_text') ?: $stored_config->get('loading_text') ?: 'Loading events…',
      '#maxlength' => 120,
      '#required' => TRUE,
    ];

    $form['container_max_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Events container maximum width'),
      '#description' => $this->t('Optionally constrain and center every LiveWhale events container. Enter a positive CSS length using px, rem, em, %, vw, vh, vmin, vmax, or ch (for example, 1200px). Leave blank for no module-defined maximum width.'),
      '#default_value' => $effective_config->get('container_max_width') ?? '',
      '#maxlength' => 32,
    ];

    $form = parent::buildForm($form, $form_state);

    if ($script_url_is_overridden) {
      $form['script_url']['#disabled'] = TRUE;
      $form['script_url']['#description'] = $this->t('This effective value is overridden outside Drupal configuration, typically in settings.php. Change the override and rebuild caches to switch environments.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $effective_config = $this->configFactory()->get('du_livewhale_events.settings');

    if (!$effective_config->hasOverrides('script_url')) {
      $script_url = trim($form_state->getValue('script_url'));

      if (!\du_livewhale_events_script_url_is_valid($script_url)) {
        $form_state->setErrorByName('script_url', $this->t('Enter an absolute HTTPS URL ending in @path.', [
          '@path' => \DU_LIVEWHALE_EVENTS_SCRIPT_PATH,
        ]));
      }
    }

    $minimum_height = filter_var($form_state->getValue('minimum_height'), FILTER_VALIDATE_INT);
    if ($minimum_height === FALSE || $minimum_height < 0 || $minimum_height > 2000) {
      $form_state->setErrorByName('minimum_height', $this->t('Enter a whole number from 0 through 2000.'));
    }

    if (trim($form_state->getValue('loading_text')) === '') {
      $form_state->setErrorByName('loading_text', $this->t('Enter loading text.'));
    }

    $container_max_width = trim($form_state->getValue('container_max_width'));
    if (!\du_livewhale_events_container_max_width_is_valid($container_max_width)) {
      $form_state->setErrorByName('container_max_width', $this->t('Enter a positive CSS length using px, rem, em, %, vw, vh, vmin, vmax, or ch, or leave the field blank.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $effective_config = $this->configFactory()->get('du_livewhale_events.settings');
    $config = $this->config('du_livewhale_events.settings');

    if (!$effective_config->hasOverrides('script_url')) {
      $config->set('script_url', trim($form_state->getValue('script_url')));
    }

    $config
      ->set('loading_placeholder_enabled', (bool) $form_state->getValue('loading_placeholder_enabled'))
      ->set('minimum_height', (int) $form_state->getValue('minimum_height'))
      ->set('loading_text', trim($form_state->getValue('loading_text')))
      ->set('container_max_width', trim($form_state->getValue('container_max_width')))
      ->save();

    // Dynamic library definitions are cached, so rebuild them immediately.
    $this->libraryDiscovery->clearCachedDefinitions();
    parent::submitForm($form, $form_state);
  }

}
