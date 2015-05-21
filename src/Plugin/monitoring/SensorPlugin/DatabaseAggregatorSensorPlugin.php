<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\DatabaseAggregatorSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\ExtendedInfoSensorPluginInterface;
use Drupal\monitoring\SensorPlugin\DatabaseAggregatorSensorPluginBase;
use Drupal\Core\Entity\DependencyTrait;

/**
 * Simple database aggregator able to query a single db table.
 *
 * @SensorPlugin(
 *   id = "database_aggregator",
 *   label = @Translation("Simple Database Aggregator"),
 *   description = @Translation("Simple database aggregator able to query a single db table."),
 *   addable = TRUE
 * )
 *
 */
class DatabaseAggregatorSensorPlugin extends DatabaseAggregatorSensorPluginBase implements ExtendedInfoSensorPluginInterface {

  use DependencyTrait;

  /**
   * The query string of the executed query.
   *
   * @var object
   */
  protected $queryString;

  /**
   * The arguments of the executed query.
   *
   * @var array
   */
  protected $queryArguments;

  /**
   * The arguments of the executed query.
   *
   * @var \Drupal\Core\Database\StatementInterface
   */
  protected $executedQuery;

  /**
   * The fetched object from the query result.
   *
   * @var mixed
   */
  protected $fetchedObject;

  /**
   * Builds simple aggregate query over one db table.
   *
   * @return \Drupal\Core\Database\Query\Select
   *   The select query object.
   */
  protected function getAggregateQuery() {
    /* @var \Drupal\Core\Database\Connection $database */
    $database = $this->getService('database');
    // Get aggregate query for the table.
    $query = $database->select($this->sensorConfig->getSetting('table'));

    $this->addAggregateExpression($query);

    // Add conditions.
    foreach ($this->getConditions() as $condition) {
      $query->condition($condition['field'], $condition['value'], isset($condition['operator']) ? $condition['operator'] : NULL);
    }

    // Apply time interval on field.
    if ($this->getTimeIntervalField() && $this->getTimeIntervalValue()) {
      $query->condition($this->getTimeIntervalField(), REQUEST_TIME - $this->getTimeIntervalValue(), '>');
    }

    return $query;
  }

  /**
   * Adds the aggregate expression to the select query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $select
   *   The database select query.
   */
  protected function addAggregateExpression(SelectInterface $select) {
    $select->addExpression('COUNT(*)', 'records_count');
  }

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    $query = $this->getAggregateQuery();
    $this->queryArguments = $query->getArguments();
    $this->executedQuery = $query->execute();
    $this->queryString = $this->executedQuery->getQueryString();
    $this->fetchedObject = $this->executedQuery->fetchObject();

    $records_count = 0;
    if (!empty($this->fetchedObject->records_count)) {
      $records_count = $this->fetchedObject->records_count;
    }

    $result->setValue($records_count);
  }

  /**
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {
    $output = [];

    // Show query.
    $output['query'] = array(
      '#type' => 'item',
      '#title' => t('Query'),
      '#markup' => '<pre>' . $this->queryString . '</pre>',
    );
    $output['arguments'] = array(
      '#type' => 'item',
      '#title' => t('Arguments'),
      '#markup' => '<pre>' . var_export($this->queryArguments, TRUE) . '</pre>',
    );

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    $schema = drupal_get_schema($this->sensorConfig->getSetting('table'));
    if ($schema) {
      $this->addDependency('module', $schema['module']);
    }
    return $this->dependencies;
  }

  /**
   * Adds UI for variables table and conditions.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $field = '';
    $field_value = '';
    $settings = $this->sensorConfig->getSettings();
    $form['table'] = array(
      '#type' => 'textfield',
      '#default_value' => $this->sensorConfig->getSetting('table'),
      '#maxlength' => 255,
      '#title' => t('Table'),
      '#required' => TRUE,
    );

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
          '#options' => $this->getConditionsOperators(),
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
   * Provides list of operators for conditions.
   *
   * @return array
   *   The operators supported.
   */
  protected function getConditionsOperators() {
    // See operators https://api.drupal.org/api/drupal/includes%21entity.inc/function/EntityFieldQuery%3A%3AaddFieldCondition/7
    return array(
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
   */
  public function conditionsReplace(array $form, FormStateInterface $form_state) {
    return $form['plugin_container']['settings']['conditions_table'];
  }

  /**
   * Adds sensor to entity when 'Add field' button is pressed.
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

    /** @var \Drupal\Core\Database\Connection $database */
    $database = $this->getService('database');
    $field_name = $form_state->getValue(array('settings', 'aggregation', 'time_interval_field'));
    if (!empty($field_name)) {
      // @todo instead of validate, switch to a form select.
      $table = $form_state->getValue(array('settings', 'table'));
      if (!$database->schema()->fieldExists($table, $field_name)) {
        $form_state->setErrorByName('settings][aggregation][time_interval_field',
          t('The specified time interval field %name does not exist.', array('%name' => $field_name)));
      }
    }
  }

}
