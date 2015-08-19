<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\ImageMissingStyleSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal;

/**
 * Monitors image derivate creation errors from dblog.
 *
 * @SensorPlugin(
 *   id = "image_style_missing",
 *   label = @Translation("Image Missing Style"),
 *   description = @Translation("Monitors image derivate creation errors from database log."),
 *   provider = "image",
 *   addable = FALSE
 * ),
 *
 * Displays image derivate with highest occurrence as message.
 */
class ImageMissingStyleSensorPlugin extends WatchdogAggregatorSensorPlugin {

  /**
   * The path of the most failed image.
   *
   * @var string
   */
  protected $sourceImagePath;

  /**
   * {@inheritdoc}
   */
  protected $configurableConditions = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $configurableVerboseOutput = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getAggregateQuery() {
    // Extends the watchdog query.
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
    parent::runSensor($result);
    if (!empty($this->fetchedObject)) {
      $variables = unserialize($this->fetchedObject->variables);
      if (isset($variables['%source_image_path'])) {
        $result->addStatusMessage($variables['%source_image_path']);
        $this->sourceImagePath = $variables['%source_image_path'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {
    $output = parent::resultVerbose($result);

    // If non found, no reason to query file_managed table.
    if ($result->getValue() == 0) {
      return $output;
    }

    // In case we were not able to retrieve this info from the watchdog
    // variables.
    if (empty($this->sourceImagePath)) {
      $message = t('Source image path is empty, cannot query file_managed table');
    }
    else {
      $query_result = \Drupal::entityQuery('file')
        ->condition('uri', $this->sourceImagePath)
        ->execute();
    }

    if (!empty($query_result)) {
      $file = file_load(array_shift($query_result));
      /** @var \Drupal\file\FileUsage\FileUsageInterface $usage */
      $usage = \Drupal::service('file.usage');
      $message = t('File managed records: <pre>@file_managed</pre>', array('@file_managed' => var_export($usage->listUsage($file), TRUE)));
    }

    if (empty($message)) {
      $message = t('File @file record not found in the file_managed table.', array('@file' => $result->getMessage()));
    }

    $output['message'] = array(
      '#type' => 'item',
      '#title' => t('Message'),
      '#markup' => $message,
    );

    return $output;
  }
}
