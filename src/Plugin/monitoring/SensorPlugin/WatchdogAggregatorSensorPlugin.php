<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\WatchdogAggregatorSensorPlugin.
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
 * Watchdog aggregator which handles replacement of variables in the message.
 *
 * @SensorPlugin(
 *   id = "watchdog_aggregator",
 *   label = @Translation("Simple Watchdog Aggregator"),
 *   description = @Translation("Simple aggregator able to query the watchdog table."),
 *   addable = TRUE
 * )
 *
 */
class WatchdogAggregatorSensorPlugin extends DatabaseAggregatorSensorPlugin implements ExtendedInfoSensorPluginInterface {
  /**
   * {@inheritdoc}
   */
  public function verboseResultUnaggregated(array &$output) {
    parent::verboseResultUnaggregated($output);
    if (isset($output['result']['#rows'])) {
      if (array_key_exists('message', $output['result']['#header']) && array_key_exists('variables', $output['result']['#header'])) {
        unset($output['result']['#header']['variables']);
      }
      foreach ($output['result']['#rows'] as $delta => $row) {
        if (array_key_exists('message', $row['data']) && array_key_exists('variables', $row['data'])) {
          $output['result']['#rows'][$delta]['data']['message'] = SafeMarkup::format($row['data']['message'], unserialize($row['data']['variables']));
          unset($output['result']['#rows'][$delta]['data']['variables']);
        };
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
    $form['table']['#disabled'] = TRUE;
    $form['aggregation']['time_interval_field']['#disabled'] = TRUE;
    return $form;
  }

}
