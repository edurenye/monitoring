<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\PhpNoticesSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\DatabaseAggregatorSensorPluginBase;

/**
 * Displays the most frequent PHP notices and errors.
 *
 * @SensorPlugin(
 *   id = "php_notices",
 *   provider = "dblog",
 *   label = @Translation("PHP notices (database log)"),
 *   description = @Translation("Displays the most frequent PHP notices and errors."),
 *   addable = FALSE
 * )
 */
class PhpNoticesSensorPlugin extends WatchdogAggregatorSensorPlugin {

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    parent::runSensor($result);
    if (!empty($this->fetchedObject->variables)) {
      $variables = unserialize($this->fetchedObject->variables);
      $variables['%file'] = $this->shortenFilename($variables['%file']);
      $result->setMessage('@count times: @error', ['@count' => (int) $this->fetchedObject->records_count, '@error' => SafeMarkup::xssFilter(SafeMarkup::format('%type: !message in %function (line %line of %file).', $variables), Xss::getAdminTagList())]);
    };
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    unset($form['conditions_table']);
    unset($form['output_table']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAggregateQuery() {
    $query = parent::getAggregateQuery();
    $query->addField('watchdog', 'variables');
    $query->condition('type', 'php', NULL);
    // The message is the most recurring php error.
    $query->groupBy('variables');
    $query->orderBy('records_count', 'DESC');
    $query->range(0, 1);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery() {
    $query = parent::getQuery();
    $query->addField('watchdog', 'variables');
    $this->addAggregateExpression($query);
    $query->condition('type', 'php', NULL);
    $query->groupBy('variables');

    // Drop the existing order, order by record count instead.
    $order = &$query->getOrderBy();
    $order = [];
    $query->orderBy('records_count', 'DESC');
    $query->range(0, 20);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildTableHeader($rows = []) {
    $header = [
      'count' => $this->t('Count'),
      'type' => $this->t('Type'),
      'message' => $this->t('Message'),
      'function' => $this->t('Caller'),
      'file' => $this->t('File'),
    ];
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildTableRows(array $results) {
    $rows = [];
    foreach ($results as $delta => $row) {
      $variables = unserialize($row->variables);
      $variables['%file'] = $this->shortenFilename($variables['%file']);
      $rows[$delta]['count'] = $row->records_count;
      $rows[$delta]['type'] = $variables['%type'];
      $rows[$delta]['message'] = SafeMarkup::xssFilter($variables['!message'], Xss::getAdminTagList());
      $rows[$delta]['function'] = $variables['%function'];
      $rows[$delta]['file'] = $variables['%file'] . ':' . $variables['%line'];
    }
    return $rows;
  }

  /**
   * Removes the root path from a filename.
   *
   * @param string $filename
   *   Name of the file.
   *
   * @return string
   *   The shortened filename.
   */
  protected function shortenFilename($filename) {
    return str_replace(DRUPAL_ROOT . '/', '', $filename);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }


}