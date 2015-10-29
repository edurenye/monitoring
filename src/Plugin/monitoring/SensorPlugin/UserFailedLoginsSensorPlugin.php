<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\UserFailedLoginsSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;

/**
 * Monitors user failed login from dblog messages.
 *
 * @SensorPlugin(
 *   id = "user_failed_logins",
 *   label = @Translation("User Failed Logins"),
 *   description = @Translation("Monitors user failed login from dblog messages."),
 *   addable = FALSE
 * )
 *
 * Helps to identify bots or brute force attacks.
 */
class UserFailedLoginsSensorPlugin extends WatchdogAggregatorSensorPlugin {

  /**
   * {@inheritdoc}
   */
  public function getAggregateQuery() {
    $query = parent::getAggregateQuery();
    $query->addField('watchdog', 'variables');
    $query->groupBy('variables');
    $query->orderBy('records_count', 'DESC');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    $records_count = 0;
    foreach ($this->getAggregateQuery()->execute() as $row) {
      $records_count += $row->records_count;
      $variables = unserialize($row->variables);
      $result->addStatusMessage('@user: @count', array('@user' => $variables['%user'], '@count' => $row->records_count));
    }

    $result->setValue($records_count);
  }

  /**
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {
    $output = [];

    // The unaggregated result in a fieldset.
    $output['unaggregated'] = array(
      '#type' => 'fieldset',
      '#title' => t('Unaggregated attempts'),
      '#attributes' => array(),
    );
    $output['unaggregated'] += parent::resultVerbose($result);

    // The result aggregated per user.
    $output['attempts_per_user'] = array(
      '#type' => 'fieldset',
      '#title' => t('Attempts per user'),
      '#attributes' => array(),
    );
    $output['attempts_per_user'] += $this->verboseResultCounting();

    return $output;
  }

  /**
   * Get the verbose results of the attempts per user.
   *
   * @return array
   *   Return the table with the attempts per user.
   */
  public function verboseResultCounting() {
    $output = [];

    if ($this->sensorConfig->getSetting('verbose_fields')) {
      $output['result_title'] = array(
        '#type' => 'item',
        '#title' => t('Result'),
      );

      // Fetch the last 20 matching entries, aggregated.
      $query_result = $this->getAggregateQuery()
        ->range(0, 20)
        ->execute();
      $this->queryString = $query_result->getQueryString();

      $rows = $this->buildTableRows($query_result->fetchAll());
      $results = [];
      foreach ($rows as $key => $row) {
        $results[$key] = [];
        $variables = unserialize($row['variables']);
        $results[$key]['user'] = $variables['%user'];
        $results[$key]['attempts'] = $row['records_count'];
      }
      $output['result'] = array(
        '#type' => 'table',
        '#rows' => $results,
        '#header' => $this->buildTableHeader($results),
        '#empty' => $this->t('There are no results for this sensor to display.'),
      );
    }

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

}
