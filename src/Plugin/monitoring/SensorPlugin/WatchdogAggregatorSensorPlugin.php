<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\WatchdogAggregatorSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\Result\SensorResultInterface;
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
  public function verboseResultUnaggregated(array &$output) {
    parent::verboseResultUnaggregated($output);
    // If sensor has message and variables, remove variables header.
    if (isset($output['result']['#rows']) && array_key_exists('message', $output['result']['#header']) && array_key_exists('variables', $output['result']['#header'])) {
      unset($output['result']['#header']['variables']);
      // Replace the message for every row.
      foreach ($output['result']['#rows'] as $delta => $row) {
        $output['result']['#rows'][$delta]['message'] = Safemarkup::xssFilter(SafeMarkup::format($row['message'], unserialize($row['variables']), Xss::getAdminTagList()));
        // Do not render the variables in the row.
        unset($output['result']['#rows'][$delta]['variables']);
      };
    }
  }

  /**
   * Adds UI for variables table and conditions.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['#title'] = t('Watchdog Sensor plugin settings');
    // The following fields should not be edited, so we disable them.
    $form['table']['#default_value'] = 'watchdog';
    $form['table']['#disabled'] = TRUE;
    $form['aggregation']['time_interval_field']['#default_value'] = 'timestamp';
    $form['aggregation']['time_interval_field']['#disabled'] = TRUE;
    return $form;
  }

}
