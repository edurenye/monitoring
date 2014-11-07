<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\Sensor\SensorDatabaseAggregator.
 */

namespace Drupal\monitoring\Plugin\monitoring\Sensor;


use Drupal\Core\Database\Query\SelectInterface;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\SensorExtendedInfoInterface;
use Drupal\monitoring\Sensor\Sensors\SensorDatabaseAggregatorBase;
use Drupal\Core\Entity\DependencyTrait;

/**
 * Simple database aggregator able to query a single db table.
 *
 * @Sensor(
 *   id = "database_aggregator",
 *   label = @Translation("Simple Database Aggregator"),
 *   description = @Translation("Simple database aggregator able to query a single db table."),
 *   addable = TRUE
 * )
 *
 */
class SensorDatabaseAggregator extends SensorDatabaseAggregatorBase implements SensorExtendedInfoInterface {
  
  use DependencyTrait;

  /**
   * The fetched object from the query result.
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

  protected $fetchedObject;

  /**
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {
    return "Query:\n{$this->queryString}\n\nArguments:\n" . var_export($this->queryArguments, TRUE);
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
    $query = $database->select($this->info->getSetting('table'));
    $this->addAggregateExpression($query);

    foreach ($this->getConditions() as $condition) {
      $query->condition($condition['field'], $condition['value'], isset($condition['operator']) ? $condition['operator'] : NULL);
    }

    if ($time_interval_field = $this->getTimeIntervalField()) {
      $query->condition($this->getTimeIntervalField(), REQUEST_TIME - $this->getTimeIntervalValue(), '>');
    }

    return $query;
  }

  /**
   * Adds the aggregate expression to the select query.
   *
   * @param SelectInterface $select
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

    $schema = drupal_get_schema($this->info->getSetting('table'));
    if ($schema) {
      $this->addDependency('module', $schema['module']);
    }
    return $this->dependencies;
  }

  /**
   * Adds UI for variables table and conditions.
   */
  public function settingsForm($form, &$form_state) {
    $form = parent::settingsForm($form, $form_state);
    $field = '';
    $field_value = '';
    $settings = $this->info->getSettings();
    $form['table'] = array(
      '#type' => 'textfield',
      '#default_value' => $this->info->getSetting('table'),
      '#maxlength' => 255,
      '#title' => t('Table'),
      '#required' => TRUE,
    );
    if (isset($this->info->settings['table'])) {
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
}
