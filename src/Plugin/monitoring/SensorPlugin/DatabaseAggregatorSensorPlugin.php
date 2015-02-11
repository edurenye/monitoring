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
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {
    $output = [];

    $output['query'] = array(
      '#type' => 'item',
      '#title' => t('Query'),
      '#markup' => $this->queryString,
    );
    $output['arguments'] = array(
      '#type' => 'item',
      '#title' => t('Arguments'),
      '#markup' => '<pre>' . var_export($this->queryArguments, TRUE) . '</pre>',
    );
    // @todo show results.

    return $output;
  }

  /**
   * Builds simple aggregate query over one db table.
   *
   * @return \Drupal\Core\Database\Query\Select
   *   The select query object.
   */
  protected function getAggregateQuery() {
    /* @var \Drupal\Core\Database\Connection $database */
    $database = $this->getService('database');
    $query = $database->select($this->sensorConfig->getSetting('table'));
    $this->addAggregateExpression($query);

    foreach ($this->getConditions() as $condition) {
      $query->condition($condition['field'], $condition['value'], isset($condition['operator']) ? $condition['operator'] : NULL);
    }

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

    if (!empty($this->fetchedObject->records_count)) {
      $records_count = $this->fetchedObject->records_count;
    }
    else {
      $records_count = 0;
    }

    $result->setValue($records_count);
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
    if (isset($this->sensorConfig->settings['table'])) {
      $field = $settings['conditions'][0]['field'];
      $field_value = $settings['conditions'][0]['value'];
    }

    $form['conditions'][0]['field'] = array(
      '#type' => 'textfield',
      '#title' => t("Condition's Field"),
      '#maxlength' => 255,
      '#default_value' => $field,
    );
    $form['conditions'][0]['value'] = array(
      '#type' => 'textfield',
      '#title' => t("Condition's Value"),
      '#maxlength' => 255,
      '#default_value' => $field_value,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValidate($form, FormStateInterface $form_state) {
    parent::settingsFormValidate($form, $form_state);

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
