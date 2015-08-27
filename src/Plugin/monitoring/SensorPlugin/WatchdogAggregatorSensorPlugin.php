<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\WatchdogAggregatorSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\Component\Utility\Xss;
use Drupal\monitoring\SensorPlugin\ExtendedInfoSensorPluginInterface;
use Drupal\Component\Utility\SafeMarkup;

/**
 * Watchdog aggregator which handles replacement of variables in the message.
 *
 * @SensorPlugin(
 *   id = "watchdog_aggregator",
 *   label = @Translation("Watchdog Aggregator"),
 *   description = @Translation("Aggregator able to query the watchdog table."),
 *   addable = TRUE
 * )
 */
class WatchdogAggregatorSensorPlugin extends DatabaseAggregatorSensorPlugin implements ExtendedInfoSensorPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $configurableTable = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $configurableTimestampField = FALSE;

  /**
   * {@inheritdoc}
   */
  public function verboseResultUnaggregated(array &$output) {
    parent::verboseResultUnaggregated($output);
    // If sensor has message and variables, remove variables header.
    if (isset($output['result']['#rows']) && array_key_exists('message', $output['result']['#header']) && array_key_exists('variables', $output['result']['#header'])) {
      unset($output['result']['#header']['variables']);
      // Replace the message for every row.
      foreach ($output['result']['#rows'] as $delta => $row) {
        $output['result']['#rows'][$delta]['#markup'] = SafeMarkup::format($row['message'], unserialize($row['variables']));
        // Do not render the raw message & variables in the row.
        unset($output['result']['#rows'][$delta]['message']);
        unset($output['result']['#rows'][$delta]['variables']);
      };
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultConfiguration() {
    $default_config = array(
      'settings' => array(
        'table' => 'watchdog',
        'time_interval_field' => 'timestamp',
      ),
    );
    return $default_config;
  }

}
