<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\DatabaseAggregatorSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\ExtendedInfoSensorPluginInterface;
use Drupal\monitoring\SensorPlugin\DatabaseAggregatorSensorPluginBase;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Component\Utility\SafeMarkup;

/**
 * Database aggregator able to query a single db table.
 *
 * @SensorPlugin(
 *   id = "database_aggregator",
 *   label = @Translation("Database Aggregator"),
 *   description = @Translation("Database aggregator able to query a single db table."),
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
   * The currently active keys for verbose output.
   *
   * @var array
   */
  protected $currentKeys;

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
   * Builds the  query for verbose output.
   *
   * Similar to the aggregate query, but without aggregation.
   *
   * @return \Drupal\Core\Database\Query\Select
   *   The select query object.
   *
   * @see getQueryAggregate()
   */
  protected function getQuery() {
    /* @var \Drupal\Core\Database\Connection $database */
    $database = $this->getService('database');
    // Get query for the table.
    $query = $database->select($this->sensorConfig->getSetting('table'));
    // Add conditions.
    foreach ($this->getConditions() as $condition) {
      $query->condition($condition['field'], $condition['value'], isset($condition['operator']) ? $condition['operator'] : NULL);
    }
    // Apply time interval on field.
    if ($this->getTimeIntervalField() && $this->getTimeIntervalValue()) {
      $query->condition($this->getTimeIntervalField(), REQUEST_TIME - $this->getTimeIntervalValue(), '>');
    }

    // Add key fields.
    $keys = $this->sensorConfig->getSetting('keys');
    if (!empty($keys)) {
      foreach ($this->sensorConfig->getSetting('keys') as $key) {
        $query->addField($this->sensorConfig->getSetting('table'), $key);
      }
    }

    return $query;
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

    $output['result_title'] = array(
      '#type' => 'item',
      '#title' => t('RESULT'),
    );

    $this->verboseResultUnaggregated($output);
    return $output;
  }

  /**
   * Adds unaggregated verbose output to the render array $output.
   *
   * @param array &$output
   *   Render array where the result will be added.
   */
  public function verboseResultUnaggregated(array &$output) {
    // Fetch the last 10 matching entries, unaggregated.
    $query_result = $this->getQuery()
      ->range(0, 10)
      ->execute();
    // Render rows.
    $rows = [];
    foreach ($query_result as $record) {
      $row = [];
      foreach ($record as $key => $value) {
        $row[$key] = $value;
      }

      $rows[] = array(
        'data' => $row,
        'class' => 'entity',
      );
    }

    if (count($rows) > 0) {
      // Provide consistent keys for header and data rows for easy altering.
      $keys = array_keys($rows[0]['data']);
      $header = array_combine($keys, $keys);
      $output['result'] = array(
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      );
    }
    else {
      $output['result'] = [
        '#type' => 'item',
        '#markup' => t('No results were found in the table.'),
      ];
    }
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
  public function calculateDependencies() {
    parent::calculateDependencies();

    // There is no API to load a list of all tables, loop through all modules
    // with a hook_schema() hook and try to find the table.
    \Drupal::moduleHandler()->loadAllIncludes('install');
    foreach (\Drupal::moduleHandler()->getImplementations('schema') as $module) {
      $schema = drupal_get_module_schema($module, $this->sensorConfig->getSetting('table'));
      if (isset($schema['module'])) {
        $this->addDependency('module', $schema['module']);
        break;
      }
    }
    return $this->dependencies;
  }

  /**
   * Adds UI for variables table and conditions.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

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
    $form['output_table'] = array(
      '#type' => 'fieldset',
      '#title' => t('Verbose Output configuration'),
      '#prefix' => '<div id="selected-output">',
      '#suffix' => '</div>',
      '#tree' => FALSE,
    );
    // Fill the keys text field with keys.
    $form['output_table']['keys'] = array(
      '#type' => 'textarea',
      '#tree' => FALSE,
      '#default_value' => implode("\n", $this->sensorConfig->getSetting('keys')),
      '#maxlength' => 255,
      '#title' => t('Keys'),
      '#required' => TRUE,
    );


    // Fill the conditions table with keys and values for each condition.
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
    foreach ($form_state->getValue('conditions') as $key => $condition) {
      if (!empty($condition['field'])) {
        $settings['conditions'][] = $condition;
      }
    }

    // Update the verbose output keys.
    try {
      $this->currentKeys = $this->sensorConfig->getSetting('keys');
      $keys = array_filter(explode("\n", $form_state->getValue('keys')));
      $keys = array_map('trim', $keys);
      $settings['keys'] = $keys;

      $this->sensorConfig->set('settings', $settings);
      $this->getQuery()->execute();
    }
    catch (DatabaseExceptionWrapper $e) {
      $settings = $this->sensorConfig->getSettings();
      $settings['keys'] = $this->currentKeys;
      $this->sensorConfig->set('settings', $settings);
      drupal_set_message('Verbose output configuration is invalid, keys were not saved.', 'error');
      drupal_set_message($e->getMessage(), 'warning');
    }
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
   *   The form structure array.
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

    /** @var \Drupal\Core\Database\Connection $database */
    $database = $this->getService('database');
    $field_name = $form_state->getValue(array(
      'settings',
      'aggregation',
      'time_interval_field',
    ));
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
