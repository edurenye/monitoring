<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\EntityAggregatorSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\Query\QueryInterface;
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
   * Local variable to store the query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryFactory;

  /**
   * Builds the entity aggregate query.
   *
   * @return \Drupal\Core\Entity\Query\QueryAggregateInterface
   *   The entity query object.
   */
  protected function getEntityQueryAggregate() {
    $entity_info = $this->entityManager->getDefinition($this->getEntityType(), TRUE);

    // Get aggregate query for the entity type.
    $query = $this->entityQueryFactory->getAggregate($this->getEntityType());
    $this->aggregateField = $entity_info->getKey('id');

    $this->addAggregate($query);

    // Add conditions.
    foreach ($this->getConditions() as $condition) {
      if (empty($condition['field'])) {
        continue;
      }
      $query->condition($condition['field'], $condition['value'], isset($condition['operator']) ? $condition['operator'] : NULL);
    }

    // Apply time interval on field.
    if ($this->getTimeIntervalField() && $this->getTimeIntervalValue()) {
      $query->condition($this->getTimeIntervalField(), REQUEST_TIME - $this->getTimeIntervalValue(), '>');
    }

    return $query;
  }

  /**
   * Builds the entity query for verbose output.
   *
   * Similar to the aggregate query, but without aggregation.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query object.
   *
   * @see getEntityQueryAggregate()
   */
  protected function getEntityQuery() {
    $entity_info = $this->entityManager->getDefinition($this->getEntityType(), TRUE);

    // Get query for the entity type.
    $query = $this->entityQueryFactory->get($this->getEntityType());

    // Add conditions.
    foreach ($this->getConditions() as $condition) {
      if (empty($condition['field'])) {
        continue;
      }
      $query->condition($condition['field'], $condition['value'], isset($condition['operator']) ? $condition['operator'] : NULL);
    }

    // Apply time interval on field.
    if ($this->getTimeIntervalField() && $this->getTimeIntervalValue()) {
      $query->condition($this->getTimeIntervalField(), REQUEST_TIME - $this->getTimeIntervalValue(), '>');
    }

    // Order by most recent or id.
    if ($this->getTimeIntervalField()) {
      $query->sort($this->getTimeIntervalField(), 'DESC');
    }
    else {
      $query->sort($entity_info->getKey('id'), 'DESC');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(SensorConfig $sensor_config, $plugin_id, $plugin_definition, EntityManagerInterface $entityManager, QueryFactory $query_factory) {
    parent::__construct($sensor_config, $plugin_id, $plugin_definition);
    $this->entityManager = $entityManager;
    $this->entityQueryFactory = $query_factory;
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
    $output = [];

    $output['field'] = array(
      '#type' => 'item',
      '#title' => t('Aggregate field'),
      '#markup' => $this->aggregateField,
    );

    // Fetch the last 10 matching entries, unaggregated.
    $entity_ids = $this->getEntityQuery()
      ->range(0, 10)
      ->execute();
    // Show query.
    // @todo show query. This needs an injected FakeStatement.
    // $query = '';
    // $output['query'] = array(
    //   '#type' => 'item',
    //   '#title' => t('Query'),
    //   '#markup' => '<pre>' . $query . '</pre>',
    // );

    // Load entities.
    $entity_type = $this->getEntityType();
    $entities = $this->entityManager
      ->getStorage($entity_type)
      ->loadMultiple($entity_ids);

    // @todo add extra fields, formatted...
    // Render entities.
    $rows = [];
    foreach ($entities as $id => $entity) {
      $row = [];
      $entity_link = array(
        '#type' => 'link',
        '#title' => $entity->label(),
        '#url' => $entity->urlInfo(),
      );

      $row[] = $entity->id();
      $row[] = \Drupal::service('renderer')->renderPlain($entity_link);
      $rows[] = array(
        'data' => $row,
        'class' => 'entity',
      );
    }
    if (count($rows) > 0) {
      $header = [];
      $header[] = t('#');
      $header[] = t('Label');

      $output['entities'] = array(
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      );
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $entity_type_id = $this->getEntityType();
    if (!$entity_type_id) {
      throw new \Exception(SafeMarkup::format('Sensor @id is missing the required entity_type setting.', array('@id' => $this->id())));
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


    // Add conditions.
    // Fieldset for sensor list elements.
    $form['conditions_table'] = array(
      '#type' => 'fieldset',
      '#title' => t('Conditions'),
      '#prefix' => '<div id="selected-conditions">',
      '#suffix' => '</div>',
      '#tree' => FALSE,
    );

    // Table for included sensors.
    $form['conditions_table']['conditions'] = array(
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => array(
        'field' => t('Field'),
        'operator' => t('Operator'),
        'value' => t('Value'),
      ),
      '#empty' => t(
        'Add conditions to filter the results.'
      ),
    );

    // Fill the sensors table with form elements for each sensor.
    $conditions = $this->sensorConfig->getSetting('conditions');
    if (empty($conditions)) {
      $conditions = [];
    }

    if (!$form_state->has('conditions_rows')) {
      $form_state->set('conditions_rows', count($conditions) + 1);
    }

    for ($i = 0; $i < $form_state->get('conditions_rows'); $i++) {
      $condition = isset($conditions[$i]) ? $conditions[$i] : array();

      $condition += array(
        'field' => '',
        'value' => '',
        'operator' => '=',
      );

      // See operators https://api.drupal.org/api/drupal/includes%21entity.inc/function/EntityFieldQuery%3A%3AaddFieldCondition/7
      $operators = array(
        '=' => t('='),
        '!=' => t('!='),
        '<' => t('<'),
        '=<' => t('=<'),
        '>' => t('>'),
        '>=' => t('>='),
        'STARTS_WITH' => t('STARTS_WITH'),
        'CONTAINS' => t('CONTAINS'),
        //'BETWEEN' => t('BETWEEN'), // Requires
        //'IN' => t('IN'),
        //'NOT IN' => t('NOT IN'),
        //'EXISTS' => t('EXISTS'),
        //'NOT EXISTS' => t('NOT EXISTS'),
        //'LIKE' => t('LIKE'),
        //'IS NULL' => t('IS NULL'),
        //'IS NOT NULL' => t('IS NOT NULL'),
      );
      $form['conditions_table']['conditions'][$i] = array(
        'field' => array(
          '#type' => 'textfield',
          '#default_value' => $condition['field'],
          '#title' => t('Field'),
          '#title_display' => 'invisible',
          '#size' => 20,
          //'#required' => TRUE,
        ),
        'operator' => array(
          '#type' => 'select',
          '#default_value' => $condition['operator'],
          '#title' => t('Operator'),
          '#title_display' => 'invisible',
          '#options' => $operators,
          //'#required' => TRUE,
        ),
        'value' => array(
          '#type' => 'textfield',
          '#default_value' => $condition['value'],
          '#title' => t('Value'),
          '#title_display' => 'invisible',
          '#size' => 40,
          //'#required' => TRUE,
        ),
      );
    }

    // Select element for available conditions.
    $form['conditions_table']['condition_add_button'] = array(
      '#type' => 'submit',
      '#value' => t('Add more conditions'),
      '#ajax' => array(
        'wrapper' => 'selected-conditions',
        'callback' => array($this, 'conditionsReplace'),
        'method' => 'replace',
      ),
      '#submit' => array(array($this, 'addConditionSubmit')),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    /** @var \Drupal\monitoring\Form\SensorForm $sensor_form */
    $sensor_form = $form_state->getFormObject();
    /** @var \Drupal\monitoring\SensorConfigInterface $sensor_config */
    $sensor_config = $sensor_form->getEntity();
    $settings = $sensor_config->getSettings();

    // Cleanup conditions, remove empty.
    $settings['conditions'] = [];
    foreach($form_state->getValue('conditions') as $key => $condition) {
      if (!empty($condition['field'])) {
        $settings['conditions'][] = $condition;
      }
    }

    $sensor_config->set('settings', $settings);
  }

    /**
   * Returns the updated 'conditions' fieldset for replacement by ajax.
   *
   * @param array $form
   *   The updated form structure array.
   * @param FormStateInterface $form_state
   *   The form state structure.
   *
   * @return array
   *   The updated form component for the selected fields.
   */
  public function conditionsReplace(array $form, FormStateInterface $form_state) {
    return $form['plugin_container']['settings']['conditions_table'];
  }

  /**
   * Adds sensor to entity when 'Add field' button is pressed.
   *
   * @param array $form
   *   The form structure array
   * @param FormStateInterface $form_state
   *   The form state structure.
   */
  public function addConditionSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();

    $form_state->set('conditions_rows', $form_state->get('conditions_rows') + 1);

    drupal_set_message(t('Condition added.'), 'status');
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

  /**
   * Add aggregation to the query.
   *
   * @param \Drupal\Core\Entity\Query\QueryAggregateInterface $query
   *   The query.
   */
  protected function addAggregate(QueryAggregateInterface $query) {
    $query->aggregate($this->aggregateField, 'COUNT');
  }

}
