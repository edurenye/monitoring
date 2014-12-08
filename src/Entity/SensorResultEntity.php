<?php
/**
 * @file
 * Contains \Drupal\monitoring\Entity\SensorResultEntity.
 */

namespace Drupal\monitoring\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * The monitoring_sensor_result entity class.
 *
 * @ContentEntityType(
 *   id = "monitoring_sensor_result",
 *   label = @Translation("Monitoring sensor result"),
 *   base_table = "monitoring_sensor_result",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "record_id",
 *     "label" = "sensor_message",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class SensorResultEntity extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['record_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Record ID'))
      ->setDescription(t('The record ID.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The record UUID.'))
      ->setReadOnly(TRUE);

    $fields['sensor_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Sensor name'))
      ->setDescription(t('The machine name of the sensor.'));

    $fields['sensor_status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Sensor status'))
      ->setDescription(t('The sensor status at the moment of the sensor run.'));

    $fields['sensor_value'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Sensor value'))
      ->setDescription(t('The sensor value at the moment of the sensor run.'));

    $fields['sensor_message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Sensor message'))
      ->setDescription(t('The sensor message reported by the sensor.'));

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Timestamp'))
      ->setDescription(t('The time that the sensor was executed.'));

    $fields['execution_time'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Execution time'))
      ->setDescription(t('The time needed for the sensor to execute in ms.'));

    return $fields;
  }

}
