<?php

/**
 * @file
 * Contains \Drupal\monitoring\SensorConfigAccessControlHandler.
 */

namespace Drupal\monitoring;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for sensor config.
 *
 * @see Drupal\monitoring\Entity\SensorConfig
 */
class SensorConfigAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, $langcode, AccountInterface $account) {
    $plugin_definition = $entity->getPlugin()->getPluginDefinition();

    if ($operation == 'delete' && !$plugin_definition['addable']) {
      return AccessResult::forbidden();
    }
    return parent::checkAccess($entity, $operation, $langcode, $account);
  }
}
