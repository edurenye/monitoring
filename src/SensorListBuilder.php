<?php

/**
 * @file
 * Contains \Drupal\monitoring\SensorListBuilder.
 */

namespace Drupal\monitoring;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Url;
use Drupal\monitoring\Entity\SensorConfig;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a class to build a listing of sensor config entities.
 *
 * @see \Drupal\monitoring\Entity\SensorConfig
 */
class SensorListBuilder extends ConfigEntityListBuilder implements FormInterface {

  /*
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['category'] = $this->t('Category');
    $header['label'] = $this->t('Label');
    $header['description'] = $this->t('Description');
    return $header + parent::buildHeader();
  }

  /*
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $this->getLabel($entity);
    $row['category'] = $entity->getCategory();
    $row['description'] = $entity->getDescription();
    $url = Url::fromRoute('monitoring.detail_form', array('monitoring_sensor_config' => $entity->id()));

    $row = $row + parent::buildRow($entity);

    // Adds the link to details page if sensor is enabled.
    $sensor_config = SensorConfig::load($entity->id());
    if ($sensor_config->isEnabled()) {
      $row['operations']['data']['#links']['details'] = array(
        'title' => 'Details',
        'url' => $url,
      );
    }
    return $row;
  }

  /*
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sensor_overview_form';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::validateForm().
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // No validation.
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return \Drupal::formBuilder()->getForm($this);
  }

  /*
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    foreach ($this->load() as $entity) {
      $row = $this->buildRow($entity);
      $options[$entity->id()] = $row;
      $default_value[$entity->id()] = $entity->isEnabled();
    }

    $form['sensors'] = array(
      '#type' => 'tableselect',
      '#header' => $this->buildHeader(),
      '#options' => $options,
      '#default_value' => $default_value,
      '#attributes' => array(
        'id' => 'monitoring-sensors-config-overview',
      ),
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Update enabled sensors'),
    );

    return $form;
  }

  /*
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('sensors') as $sensor_id => $enabled) {
      $sensor = SensorConfig::load($sensor_id);
      if ($enabled) {
        $sensor->status = TRUE;
      }
      else {
        $sensor->status = FALSE;
      }
      $sensor->save();
    }
    drupal_set_message($this->t('Configuration has been saved.'));
  }
}
