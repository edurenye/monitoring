<?php
/**
 * @file
 * Contains \Drupal\monitoring\Entity\SensorConfig.
 */

namespace Drupal\monitoring\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Represents a sensor config entity class.
 *
 * @todo more
 * @ConfigEntityType(
 *   id = "monitoring_sensor_config",
 *   label = @Translation("Monitoring Sensor"),
 *   handlers = {
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
class SensorConfig extends ConfigEntityBase {

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
  public $sensor_id;

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
   * The sensor value numeric flag.
   *
   * @var bool
   */
  public $numeric = TRUE;

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
   * The sensor ID.
   *
   * @var string
   */

  public function getName() {
    return $this->id;
  }

  /**
   * Gets sensor label.
   *
   * The sensor label might not be self-explaining enough or unique without
   * the category, the category should always be present when the label is
   * displayed.
   *
   * @return string
   *   Sensor label.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Gets sensor description.
   *
   * @return string
   *   Sensor description.
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Gets sensor class.
   *
   * @return string
   *   Sensor class
   */
  public function getSensorClass() {
    $definition = monitoring_sensor_manager()->getDefinition($this->sensor_id);
    return $definition['class'];
  }

  /**
   * Gets the sensor plugin.
   *
   * @return \Drupal\monitoring\Sensor\SensorInterface
   *   Instantiated sensor.
   */
  public function getPlugin() {
    $configuration = array('sensor_info' => $this);
    $sensor = monitoring_sensor_manager()->createInstance($this->sensor_id, $configuration);
    return $sensor;
  }

  /**
   * Gets sensor categories.
   *
   * @return string
   *   Categories.
   */
  public function getCategory() {
    return $this->category;
  }

  /**
   * Gets sensor value label.
   *
   * In case the sensor defines value_type, it will use the label provided for
   * that type by monitoring_value_types().
   *
   * Next it searches for the label within the sensor definition value_label.
   *
   * If nothing is defined, it returns NULL.
   *
   * @return string|null
   *   Sensor value label.
   */
  public function getValueLabel() {
    if ($this->value_label) {
      return $this->value_label;
    }
    if ($this->value_type) {
      $value_types = monitoring_value_types();
      return $value_types[$this->value_type]['label'];
    }
  }

  /**
   * Gets sensor value type.
   *
   * @return string|null
   *   Sensor value type.
   *
   * @see monitoring_value_types().
   */
  public function getValueType() {
    return $this->value_type;
  }

  /**
   * Determines if the sensor value is numeric.
   *
   * @return bool
   *   TRUE if the sensor value is numeric.
   */
  public function isNumeric() {
    return $this->numeric;
  }

  /**
   * Determines if the sensor value type is boolean.
   *
   * @return bool
   *   TRUE if the sensor value type is boolean.
   */
  public function isBool() {
    return  $this->getValueType() == 'bool';
  }

  /**
   * Gets sensor caching time.
   *
   * @return int
   *   Caching time in seconds.
   */
  public function getCachingTime() {
    return $this->caching_time;
  }

  /**
   * Gets configured threshold type.
   *
   * Defaults to none.
   *
   * @return string|null
   *   Threshold type.
   */
  public function getThresholdsType() {
    if (!empty($this->settings['thresholds']['type'])) {
      return $this->settings['thresholds']['type'];
    }

    return 'none';
  }

  /**
   * Gets the configured threshold value.
   *
   * @param string $key
   *   Name of the threshold, for example warning or critical.
   *
   * @return int|null
   *   The threshold value or NULL if not configured.
   */
  public function getThresholdValue($key) {
    if (isset($this->settings['thresholds'][$key]) && $this->settings['thresholds'][$key] !== '') {
      return $this->settings['thresholds'][$key];
    }
  }

  /**
   * Gets all settings.
   *
   * @return array
   *   Settings as an array.
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * Gets the time interval value.
   *
   * @return int
   *   Number of seconds of the time interval.
   *   NULL in case the sensor does not define the time interval.
   */
  public function getTimeIntervalValue() {
    return $this->getSetting('time_interval_value', NULL);
  }

  /**
   * Gets the setting of a key.
   *
   * @param string $key
   *   Setting key.
   * @param mixed $default
   *   Default value if the setting does not exist.
   *
   * @return mixed
   *   Setting value.
   */
  public function getSetting($key, $default = NULL) {
    return isset($this->settings[$key]) ? $this->settings[$key] : $default;
  }

  /**
   * Checks if sensor is enabled.
   *
   * @return bool
   */
  public function isEnabled() {
    return (boolean) $this->status;
  }

  /**
   * Checks if sensor is configurable.
   *
   * @return bool
   */
  public function isConfigurable() {
    return in_array('Drupal\monitoring\Sensor\SensorConfigurableInterface', class_implements($this->getSensorClass()));
  }

  /**
   * Checks if sensor provides extended info.
   *
   * @return bool
   */
  public function isExtendedInfo() {
    return in_array('Drupal\monitoring\Sensor\SensorExtendedInfoInterface', class_implements($this->getSensorClass()));
  }

  /**
   * Checks if sensor defines thresholds.
   *
   * @return bool
   */
  public function isDefiningThresholds() {
    return in_array('Drupal\monitoring\Sensor\SensorThresholdsInterface', class_implements($this->getSensorClass()));
  }

  /**
   * Compiles sensor values to an associative array.
   *
   * @return array
   *   Sensor config associative array.
   */
  public function getDefinition() {
    $config = array(
      'sensor' => $this->getName(),
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
