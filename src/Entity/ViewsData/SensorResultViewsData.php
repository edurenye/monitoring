<?php
/**
 * @file
 * Contains \Drupal\monitoring\Entity\ViewsData\SensorResultViewsData.
 */

namespace Drupal\monitoring\Entity\ViewsData;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the message entity type.
 */
class SensorResultViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();
    return $data;
  }

}
