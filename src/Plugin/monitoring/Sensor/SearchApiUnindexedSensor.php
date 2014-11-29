<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\Sensor\SearchApiUnindexedSensor.
 */

namespace Drupal\monitoring\Plugin\monitoring\Sensor;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\ThresholdsSensorBase;
use Drupal\search_api\Entity\Index;

/**
 * Monitors unindexed items for a search api index.
 *
 * @Sensor(
 *   id = "search_api_unindexed",
 *   label = @Translation("Unindexed Search Items"),
 *   description = @Translation("Monitors unindexed items for a search api index."),
 *   provider = "search_api",
 *   addable = FALSE
 * )
 *
 * Every instance represents a single index.
 *
 * Once all items are processed, the value should be 0.
 *
 * @see search_api_index_status()
 */
class SearchApiUnindexedSensor extends ThresholdsSensorBase {

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    $index = Index::load($this->sensorConfig->getSetting('index_id'));

    /* @var \Drupal\search_api\Tracker\TrackerInterface $tracker */
    $tracker = $index->getTracker();

    // Set amount of unindexed items.
    $result->setValue($tracker->getRemainingItemsCount());
  }

}
