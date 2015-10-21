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
    $query->groupBy('watchdog.variables');
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

}
