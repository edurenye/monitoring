<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\Sensor\Dblog404Sensor.
 */

namespace Drupal\monitoring\Plugin\monitoring\Sensor;

use Drupal\monitoring\Result\SensorResultInterface;

/**
 * Monitors 404 page errors from dblog.
 *
 * @Sensor(
 *   id = "dblog_404",
 *   provider = "dblog",
 *   label = @Translation("404 page errors (database log)"),
 *   description = @Translation("Monitors 404 page errors from database log."),
 *   addable = FALSE
 * )
 *
 * Displays URL with highest occurrence as message.
 */
class Dblog404Sensor extends DatabaseAggregatorSensor {

  /**
   * {@inheritdoc}
   */
  public function getAggregateQuery() {
    $query = parent::getAggregateQuery();
    $query->addField('watchdog', 'message');
    // The message is the requested 404 URL.
    $query->groupBy('message');
    $query->orderBy('records_count', 'DESC');
    $query->range(0, 1);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    parent::runSensor($result);
    if (!empty($this->fetchedObject) && !empty($this->fetchedObject->message)) {
      $result->addStatusMessage($this->fetchedObject->message);
    }
  }
}
