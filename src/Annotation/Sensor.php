<?php

/**
 * @file
 * Contains \Drupal\monitoring\Annotation\Sensor.
 */

namespace Drupal\monitoring\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines Sensor annotation object for reference by Sensor Plugins.
 *
 * @Annotation
 */
class Sensor extends Plugin {

   /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation (optional)
   */
  public $description = '';

  /**
   * The provider of the annotated class.
   *
   * @var string
   */
  public $provider;

  /**
   * Whether plugin instances can be created or not.
   *
   * @var boolean
   */
  public $addable;
}
