<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\SearchApiUnindexedSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\ThresholdsSensorPluginBase;
use Drupal\search_api\Entity\Index;

/**
 * Monitors unindexed items for a search api index.
 *
 * @SensorPlugin(
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
class SearchApiUnindexedSensorPlugin extends ThresholdsSensorPluginBase {

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
