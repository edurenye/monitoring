<?php
/**
 * @file
 * Contains \Drupal\monitoring\Entity\SensorConfig.
 */

namespace Drupal\monitoring\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\monitoring\SensorConfigInterface;

/**
 * Represents a sensor config entity class.
 *
 * @todo more
 *
 * @ConfigEntityType(
 *   id = "monitoring_sensor_config",
 *   label = @Translation("Monitoring Sensor"),
 *   handlers = {
 *     "access" = "Drupal\monitoring\SensorConfigAccessControlHandler",
 *     "list_builder" = "Drupal\monitoring\SensorListBuilder",
 *     "form" = {
 *       "add" = "Drupal\monitoring\Form\SensorForm",
 *       "delete" = "Drupal\monitoring\Form\SensorDeleteForm",
 *       "edit" = "Drupal\monitoring\Form\SensorForm",
 *       "details" = "Drupal\monitoring\Form\SensorDetailForm"
 *     }
 *   },
 *   admin_permission = "administer monitoring",
 *   config_prefix = "sensor_config",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "delete-form" = "monitoring.sensor_delete",
 *     "edit-form" = "monitoring.sensor_edit",
 *     "details-form" = "monitoring.detail_form",
 *     "force-run-form" = "monitoring.force_run_sensor"
 *   }
 * )
 */
class SensorConfig extends ConfigEntityBase implements SensorConfigInterface {

  /**
   * The config id.
   *
   * @var string
   */
  public $id;

  /**
   * The sensor label.
   *
   * @var string
   */
  public $label;

  /**
   * The sensor description.
   *
   * @var string
   */
  public $description = '';

  /**
   * The sensor category.
   *
   * @var string
   */
  public $category = 'Other';

  /**
   * The sensor id.
   *
   * @var string
   */
  public $plugin_id;

  /**
   * The sensor result class.
   *
   * @var string
   */
  public $result_class;

  /**
   * The sensor settings.
   *
   * @var array
   */
  public $settings = array();

  /**
   * The sensor value label.
   *
   * @var string
   */
  public $value_label;

  /**
   * The sensor value type.
   *
   * @var string
   */
  public $value_type;

  /**
   * The sensor caching time.
   *
   * @var integer
   */
  public $caching_time;

  /**
   * The sensor enabled/disabled flag.
   *
   * @var bool
   */
  public $status = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }
  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getSensorClass() {
    $definition = monitoring_sensor_manager()->getDefinition($this->plugin_id);
    return $definition['class'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    $configuration = array('sensor_config' => $this);
    $plugin = monitoring_sensor_manager()->createInstance($this->plugin_id, $configuration);
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getCategory() {
    return $this->category;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueLabel() {
    if ($this->value_label) {
      return $this->value_label;
    }
    if ($this->value_type) {
      $value_types = monitoring_value_types();
      if (isset($value_types[$this->value_type]['value_label'])) {
        return $value_types[$this->value_type]['value_label'];
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueType() {
    return $this->value_type;
  }

  /**
   * {@inheritdoc}
   */
  public function isNumeric() {
    $value_types = monitoring_value_types();
    if (empty($this->value_type)) {
      return FALSE;
    }
    return $value_types[$this->value_type]['numeric'];
  }

  /**
   * {@inheritdoc}
   */
  public function isBool() {
    return $this->getValueType() == 'bool';
  }

  /**
   * {@inheritdoc}
   */
  public function getCachingTime() {
    return $this->caching_time;
  }

  /**
   * {@inheritdoc}
   */
  public function getThresholdsType() {
    if (!empty($this->settings['thresholds']['type'])) {
      return $this->settings['thresholds']['type'];
    }

    return 'none';
  }

  /**
   * {@inheritdoc}
   */
  public function getThresholdValue($key) {
    if (isset($this->settings['thresholds'][$key]) && $this->settings['thresholds'][$key] !== '') {
      return $this->settings['thresholds'][$key];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeIntervalValue() {
    return $this->getSetting('time_interval_value', NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($key, $default = NULL) {
    return isset($this->settings[$key]) ? $this->settings[$key] : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (boolean) $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function isExtendedInfo() {
    return in_array('Drupal\monitoring\SensorPlugin\ExtendedInfoSensorPluginInterface', class_implements($this->getSensorClass()));
  }

  /**
   * {@inheritdoc}
   */
  public function isDefiningThresholds() {
    return in_array('Drupal\monitoring\SensorPlugin\ThresholdsSensorPluginInterface', class_implements($this->getSensorClass()));
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition() {
    $config = array(
      'sensor' => $this->id(),
      'label' => $this->getLabel(),
      'category' => $this->getCategory(),
      'description' => $this->getDescription(),
      'numeric' => $this->isNumeric(),
      'value_label' => $this->getValueLabel(),
      'caching_time' => $this->getCachingTime(),
      'time_interval' => $this->getTimeIntervalValue(),
      'enabled' => $this->isEnabled(),
    );

    if ($this->isDefiningThresholds()) {
      $config['thresholds'] = $this->getSetting('thresholds');
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    /**
     * @var SensorConfig $a
     * @var SensorConfig $b
     */
    // Checks whether both labels and categories are equal.
    if ($a->getLabel() == $b->getLabel() && $a->getCategory() == $b->getCategory()) {
      return 0;
    }
    // If the categories are not equal, their order is determined.
    elseif ($a->getCategory() != $b->getCategory()) {
      return ($a->getCategory() < $b->getCategory()) ? -1 : 1;
    }
    // In the end, the label's order is determined.
    return ($a->getLabel() < $b->getLabel()) ? -1 : 1;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    // Include the module of the sensor plugin as dependency and also allow it
    // to add additional dependencies based on the configuration.
    $instance = $this->getPlugin();
    $definition = $instance->getPluginDefinition();
    $this->addDependency('module', $definition['provider']);
    // If a plugin is configurable, calculate its dependencies.
    if ($plugin_dependencies = $instance->calculateDependencies()) {
      $this->addDependencies($plugin_dependencies);
    }
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    \Drupal::service('monitoring.sensor_runner')->resetCache(array($this->id));
  }

}
