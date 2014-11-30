<?php
/**
 * @file
 *   Contains \Drupal\monitoring\Form\SensorForm.
 */

namespace Drupal\monitoring\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\monitoring\Entity\SensorConfig;
use Drupal\monitoring\Sensor\ConfigurableSensorInterface;
use Drupal\monitoring\Sensor\SensorInterface;

/**
 * Sensor settings form controller.
 */
class SensorForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#tree'] = TRUE;

    /* @var SensorConfig $sensor_config */
    $sensor_config = $this->entity;

    $form['category'] = array(
      '#type' => 'textfield',
      '#title' => t('Category'),
      '#maxlength' => 255,
      '#default_value' => $sensor_config->getCategory(),
    );

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#maxlength' => 255,
      '#default_value' => $sensor_config->getLabel(),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#title' => t('ID'),
      '#maxlength' => 255,
      '#default_value' => $sensor_config->id(),
      '#description' => t("ID of the sensor"),
      '#required' => TRUE,
      '#disabled' => !$sensor_config->isNew(),
      '#machine_name' => array(
        'exists' => 'Drupal\monitoring\Entity\SensorConfig::load',
      ),
    );

    $form['description'] = array(
      '#type' => 'textfield',
      '#title' => t('Description'),
      '#maxlength' => 255,
      '#default_value' => $sensor_config->getDescription(),
    );

    $form['value_label'] = array(
      '#type' => 'textfield',
      '#title' => t('Value Label'),
      '#maxlength' => 255,
      '#default_value' => $sensor_config->getValueLabel(),
      '#description' => t("The value label represents the units of the sensor value."),
    );

    $form['caching_time'] = array(
      '#type' => 'number',
      '#title' => t('Cache Time'),
      '#maxlength' => 10,
      '#default_value' => $sensor_config->getCachingTime(),
      '#description' => t("The caching time for the sensor in seconds. Empty to disable caching."),
    );

    if ($sensor_config->isNew()) {
      $plugin_types = array();
      foreach (monitoring_sensor_manager()->getDefinitions() as $plugin_id => $definition) {
        if ($definition['addable'] == TRUE) {
          $plugin_types[$plugin_id] = (string) $definition['label'];
        }
      }
      uasort($plugin_types, 'strnatcasecmp');
      $form['sensor_id'] = array(
        '#type' => 'select',
        '#options' => $plugin_types,
        '#title' => $this->t('Sensor Plugin'),
        '#limit_validation_errors' => array(array('sensor_id')),
        '#submit' => array('::submitSelectPlugin'),
        '#required' => TRUE,
        '#executes_submit_callback' => TRUE,
        '#ajax' => array(
          'callback' => '::updateSelectedPluginType',
          'wrapper' => 'monitoring-sensor-plugin',
          'method' => 'replace',
        ),
      );

      $form['update'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Select sensor'),
        '#limit_validation_errors' => array(array('sensor_id')),
        '#submit' => array('::submitSelectPlugin'),
        '#attributes' => array('class' => array('js-hide')),
      );

    }
    else {
      // Set the sensor object into $form_state to make it available for
      // validate and submit callbacks.
      $form_state->set('sensor_object', $sensor_config->getPlugin());

      // @todo odd name but this can not be set to sensor_id.
      $form['old_sensor_id'] = array(
        '#type' => 'item',
        '#title' => t('Sensor Plugin'),
        '#maxlength' => 255,
        '#markup' => monitoring_sensor_manager()->getDefinition($sensor_config->sensor_id)['label']->render(),
      );
    }

    // If sensor provides settings form, automatically provide settings to
    // enable the sensor.
    $form['status'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#description' => t('Check to have the sensor trigger.'),
      '#default_value' => $sensor_config->status(),
    );

    if (isset($sensor_config->sensor_id) && $sensor_config->getPlugin() instanceof ConfigurableSensorInterface) {
      $form['settings'] = array(
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => t('Sensor plugin settings'),
        '#prefix' => '<div id="monitoring-sensor-plugin">',
        '#suffix' => '</div>',
      );
      $form['settings'] += (array) $sensor_config->getPlugin()->settingsForm($form['settings'], $form_state);
    }
    else {
      $form['settings'] = array(
        '#type' => 'container',
        '#prefix' => '<div id="monitoring-sensor-plugin">',
        '#suffix' => '</div>',
      );
    }
    $settings = $sensor_config->getSettings();
    foreach ($settings as $key => $value) {
      if (!isset($form['settings'][$key])) {
        $form['settings'][$key] = array(
          '#type' => 'value',
          '#value' => $value
        );
      }
    }
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    );

    return $form;
  }

  /**
   * Handles switching the configuration type selector.
   */
  public function updateSelectedPluginType($form, FormStateInterface $form_state) {
    return $form['settings'];
  }

  /**
   * Handles submit call when sensor type is selected.
   */
  public function submitSelectPlugin(array $form, FormStateInterface $form_state) {
    $this->entity = $this->buildEntity($form, $form_state);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    parent::validate($form, $form_state);

    /* @var SensorConfig $sensor_config */
    $sensor_config = $this->entity;
    if ($sensor_config->isNew()) {
      $plugin = $form_state->getValue('sensor_id');
      $sensor = monitoring_sensor_manager()->createInstance($plugin, array('sensor_info' => $this->entity));
    }
    else {
      $sensor = $sensor_config->getPlugin();
    }

    if ($sensor instanceof ConfigurableSensorInterface) {
      $sensor->settingsFormValidate($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var SensorConfig $sensor_config */
    $sensor_config = $this->entity;
    $sensor = $sensor_config->getPlugin();

    if ($sensor instanceof ConfigurableSensorInterface) {
      $sensor->settingsFormSubmit($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);

    $form_state->setRedirectUrl(new Url('monitoring.sensors_overview_settings'));
    drupal_set_message($this->t('Sensor settings saved.'));
  }

  /**
   * Settings form page title callback.
   *
   * @param SensorConfig $monitoring_sensor_config
   *   The Sensor config.
   *
   * @return string
   */
  public function formTitle(SensorConfig $monitoring_sensor_config) {
    return $this->t('@label settings (@category)', array('@category' => $monitoring_sensor_config->getCategory(), '@label' => $monitoring_sensor_config->getLabel()));
  }
}
