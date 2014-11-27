<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\Sensor\SensorEnabledModules.
 */

namespace Drupal\monitoring\Plugin\monitoring\Sensor;

use Drupal\Component\Utility\String;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\SensorConfigurable;
use Drupal;
use Drupal\Core\Form\FormStateInterface;

/**
 * Monitors installed modules.
 *
 * @Sensor(
 *   id = "monitoring_enabled_modules",
 *   label = @Translation("Enabled Modules"),
 *   description = @Translation("Monitors installed modules."),
 *   addable = FALSE
 * )
 *
 */
class SensorEnabledModules extends SensorConfigurable {


  /**
   * {@inheritdoc}
   */
  public function settingsForm($form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    module_load_include('inc', 'system', 'system.admin');

    $form['allow_additional'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow additional modules to be enabled'),
      '#description' => t('If checked the additional modules being enabled will not be considered as an error state.'),
      '#default_value' => $this->info->getSetting('allow_additional'),
    );

    // Get current list of available modules.
    // @todo find a faster solution? If that happens we can drop caching the
    //   result for 1 hour.
    $modules = system_rebuild_module_data();

    uasort($modules, 'system_sort_modules_by_info_name');

    $default_value = array_filter($this->info->getSetting('modules', NULL));
    // array_filter is needed to get rid off default empty setting.
    // See monitoring.sensor.monitoring_enabled_modules.yml
    if (empty($default_value)) {
      $enabled_modules = Drupal::moduleHandler()->getModuleList();
      // Reduce to the module name only.
      $default_value = array_combine(array_keys($enabled_modules), array_keys($enabled_modules));
    }

    $visible_modules = array();
    $visible_default_value = array();
    $hidden_modules = array();
    $hidden_default_value = array();

    foreach ($modules as $module => $module_data) {
      // Skip profiles.
      if (strpos(drupal_get_path('module', $module), 'profiles') === 0) {
        continue;
      }
      // As we also include hidden modules, some might have no name at all,
      // make sure it is set.
      if (!isset($module_data->info['name'])) {
        $module_data->info['name'] = '- No name -';
      }
      if (!empty($module_data->info['hidden'])) {
        $hidden_modules[$module] = $module_data->info['name'] . ' (' . $module . ')';
        if (!empty($default_value[$module])) {
          $hidden_default_value[$module] = $default_value[$module];
        }
      }
      else {
        $visible_modules[$module] = $module_data->info['name'] . ' (' . $module . ')';
        if (!empty($default_value[$module])) {
          $visible_default_value[$module] = $default_value[$module];
        }
      }
    }

    $form['modules'] = array(
      '#type' => 'checkboxes',
      '#options' => $visible_modules,
      '#title' => t('Modules expected to be enabled'),
      '#description' => t('Check all modules that are supposed to be enabled.'),
      '#default_value' => $visible_default_value,
    );

    $form['extended'] = array(
      '#type' => 'details',
      '#title' => 'Extended',
      '#open' => count($hidden_default_value) ? TRUE : FALSE,
    );

    $form['extended']['modules_hidden'] = array(
      '#type' => 'checkboxes',
      '#options' => $hidden_modules,
      '#title' => t('Hidden modules expected to be enabled'),
      '#default_value' => $hidden_default_value,
      '#description' => t('Check all modules that are supposed to be enabled.'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormSubmit($form, FormStateInterface $form_state) {
    $sensor_config = $form_state->getFormObject()->getEntity();

    parent::settingsFormSubmit($form, $form_state);

    $modules = $form_state->getValue(array('settings', 'modules'));
    $hidden_modules = $form_state->getValue(array(
      'settings', 'extended', 'modules_hidden'));
    $modules = array_merge(array_filter($modules), array_filter($hidden_modules));
    unset($sensor_config->settings['extended']);
    $sensor_config->settings['modules'] = $modules;

    return $form_state;
  }

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    // Load the info from the system table to display the label.
    $result->setExpectedValue(0);
    $delta = 0;

    $modules = system_rebuild_module_data();
    $names = array();
    foreach ($modules as $name => $module) {
      $names[$name] = $module->info['name'];
    }

    $monitoring_enabled_modules = array();
    // Filter out install profile.
    foreach (array_keys(Drupal::moduleHandler()->getModuleList()) as $module) {
      $path_parts = explode('/', drupal_get_path('module', $module));
      if ($path_parts[0] != 'profiles') {
        $monitoring_enabled_modules[$module] = $module;
      }
    }

    $expected_modules = array_filter($this->info->getSetting('modules'));

    // If there are no expected modules, the sensor is not configured, so init
    // the expected modules list as currently enabled modules.
    if (empty($expected_modules)) {
      $expected_modules = $monitoring_enabled_modules;
      $this->info->settings['modules'] = $monitoring_enabled_modules;
      $this->info->save();
    }

    // Check for modules not being installed but expected.
    $non_installed_modules = array_diff($expected_modules, $monitoring_enabled_modules);
    if (!empty($non_installed_modules)) {
      $delta += count($non_installed_modules);
      $non_installed_modules_info = array();
      foreach ($non_installed_modules as $non_installed_module) {
        if (isset($names[$non_installed_module])) {
          $non_installed_modules_info[] = $names[$non_installed_module] . ' (' . $non_installed_module . ')';
        }
        else {
          $non_installed_modules_info[] = String::format('@module_name (unknown)', array('@module_name' => $non_installed_module));
        }
      }
      $result->addStatusMessage('Following modules are expected to be installed: @modules', array('@modules' => implode(', ', $non_installed_modules_info)));
    }

    // In case we do not allow additional modules check for modules installed
    // but not expected.
    $unexpected_modules = array_diff($monitoring_enabled_modules, $expected_modules);
    if (!$this->info->getSetting('allow_additional') && !empty($unexpected_modules)) {
      $delta += count($unexpected_modules);
      $unexpected_modules_info = array();
      foreach ($unexpected_modules as $unexpected_module) {
        $unexpected_modules_info[] = $names[$unexpected_module] . ' (' . $unexpected_module . ')';
      }
      $result->addStatusMessage('Following modules are NOT expected to be installed: @modules', array('@modules' => implode(', ', $unexpected_modules_info)));
    }

    $result->setValue($delta);
  }

}
