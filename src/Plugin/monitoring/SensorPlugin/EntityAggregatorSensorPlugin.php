<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\EntityAggregatorSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\Component\Utility\String;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData;
use Drupal\monitoring\Entity\SensorConfig;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\DatabaseAggregatorSensorPluginBase;
use Drupal\monitoring\SensorPlugin\ExtendedInfoSensorPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity database aggregator.
 *
 * It utilises the entity query aggregate functionality.
 *
 * @SensorPlugin(
 *   id = "entity_aggregator",
 *   label = @Translation("Entity Aggregator"),
 *   description = @Translation("Utilises the entity query aggregate functionality."),
 *   addable = TRUE
 * )
 */
class EntityAggregatorSensorPlugin extends DatabaseAggregatorSensorPluginBase implements ExtendedInfoSensorPluginInterface {

  use DependencySerializationTrait;
  use DependencyTrait;

  /**
   * Local variable to store the field that is used as aggregate.
   *
   * @var string
   *   Field name.
   */
  protected $aggregateField;

  /**
   * Local variable to store \Drupal::entityManger().
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Local variable to store \Drupal::entityQueryAggregate.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryAggregate;

  /**
   * Builds the entity aggregate query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query object.
   */
  protected function getEntityQueryAggregate() {
    $entity_info = $this->entityManager->getDefinition($this->getEntityType(), TRUE);

    $query = $this->entityQueryAggregate->getAggregate($this->getEntityType());
    $this->aggregateField = $entity_info->getKey('id');
    $query->aggregate($this->aggregateField, 'COUNT');

    foreach ($this->getConditions() as $condition) {
      $query->condition($condition['field'], $condition['value'], isset($condition['operator']) ? $condition['operator'] : NULL);
    }

    if ($this->getTimeIntervalField() && $this->getTimeIntervalValue()) {
      $query->condition($this->getTimeIntervalField(), REQUEST_TIME - $this->getTimeIntervalValue(), '>');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(SensorConfig $sensor_config, $plugin_id, $plugin_definition, EntityManagerInterface $entityManager, QueryFactory $entity_query) {
    parent::__construct($sensor_config, $plugin_id, $plugin_definition);
    $this->entityManager = $entityManager;
    $this->entityQueryAggregate = $entity_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, SensorConfig $sensor_config, $plugin_id, $plugin_definition) {
    return new static(
      $sensor_config,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('entity.query')
    );
  }

  /**
   * Gets the entity type setting.
   *
   * @return string
   *   The entity type.
   */
  protected function getEntityType() {
    return $this->sensorConfig->getSetting('entity_type');
  }

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    $query_result = $this->getEntityQueryAggregate()->execute();
    $entity_type = $this->getEntityType();
    $entity_info = $this->entityManager->getDefinition($entity_type);

    if (isset($query_result[0][$entity_info->getKey('id') . '_count'])) {
      $records_count = $query_result[0][$entity_info->getKey('id') . '_count'];
    }
    else {
      $records_count = 0;
    }

    $result->setValue($records_count);
  }

  /**
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {
    return String::format('Aggregate field @field', array('@field' => $this->aggregateField));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $entity_type_id = $this->getEntityType();
    if (!$entity_type_id) {
      throw new \Exception(String::format('Sensor @id is missing the required entity_type setting.', array('@id' => $this->id())));
    }
    $entity_type = $this->entityManager->getDefinition($entity_type_id);
    $this->addDependency('module', $entity_type->getProvider());
    return $this->dependencies;
  }

  /**
   * Adds UI for variables entity_type and conditions.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $settings = $this->sensorConfig->getSettings();
    $conditions = array(array('field' => '', 'value' => ''));
    if (isset($settings['conditions'])) {
      $conditions = $settings['conditions'];
    }
    $form['entity_type'] = array(
      '#type' => 'select',
      '#default_value' => $this->getEntityType(),
      '#maxlength' => 255,
      '#options' => $this->entityManager->getEntityTypeLabels(),
      '#title' => t('Entity Type'),
    );
    if (!isset($settings['entity_type'])) {
      $form['entity_type']['#required'] = TRUE;
    }

    /*    if (isset($form_state['values']['settings']['conditions']['table'])) {
      $conditions = $form_state['values']['settings']['conditions']['table'];
    }

    $form['conditions'] = array(
      '#type' => 'fieldset',
      '#title' => t('Conditions'),
      '#prefix' => '<div id="add-conditions">',
      '#suffix' => '</div>',
    );

    $form['conditions']['conditions_add_button'] = array(
      '#type' => 'submit',
      '#value' => t('Add Condition'),
      '#ajax' => array(
        'wrapper' => 'add-conditions',
        'callback' => array($this, 'addConditions'),
        'method' => 'replace',
      ),
      '#submit' => array(
        array($this, 'addConditionSubmit'),
      ),
    );

    $form['conditions']['table'] = array(
      '#type' => 'table',
      '#header' => array(
        'no' => t('Condition No.'),
        'field' => t('Field'),
        'value' => t('Value'),
      ),
      '#prefix' => '<div id="add-conditions">',
      '#suffix' => '</div>',
      '#empty' => t(
        'Add Conditions to this sensor.'
      ),
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'sensors-table-weight',
        ),
      ),
    );
    */
    foreach ($conditions as $no => $condition) {
      $form['conditions'][$no] = array(
        'field' => array(
          '#type' => 'textfield',
          '#title' => t("Condition's Field"),
          '#default_value' => $condition['field'],
        ),
        'value' => array(
          '#type' => 'textfield',
          '#title' => t("Condition's Value"),
          '#default_value' => $condition['value'],
        )
      );
    }
    return $form;
  }

  /**
   * Returns the rebuild form.
   */
  public function addConditions(array $form, FormStateInterface $form_state) {
    return $form['settings']['conditions'];
  }

  /**
   * Adds new condition field and value to the form.
   */
  public function addConditionSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    if (!$form_state->hasValue(array('settings', 'conditions', 'table'))) {
      $form_state->setValue(array('settings', 'conditions', 'table'), array(array('field' => '', 'value' => '')));
    }
  }
  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $field_name = $form_state->getValue(array('settings', 'aggregation', 'time_interval_field'));
    if (!empty($field_name)) {
      // @todo instead of validate, switch to a form select.
      $entity_type = $form_state->getValue(array('settings', 'entity_type'));
      $entity_info = $this->entityManager->getFieldStorageDefinitions($entity_type);
      $data_type = NULL;
      if (!empty($entity_info[$field_name])) {
        $data_type = $entity_info[$field_name]->getPropertyDefinition('value')->getDataType();

      }
      if ($data_type != 'timestamp') {
        $form_state->setErrorByName('settings][aggregation][time_interval_field',
          t('The specified time interval field %name does not exist or is not type timestamp.', array('%name' => $field_name)));
      }
    }
  }
}
