<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\GitDirtyTreeSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\SensorPluginBase;
use Drupal\monitoring\SensorPlugin\ExtendedInfoSensorPluginInterface;

/**
 * Monitors the git repository for dirty files.
 *
 * @SensorPlugin(
 *   id = "monitoring_git_dirty_tree",
 *   label = @Translation("Git Dirty Tree"),
 *   description = @Translation("Monitors the git repository for dirty files."),
 *   addable = FALSE
 * )
 *
 * Tracks both changed and untracked files.
 * Also supports git submodules.
 *
 * Limitations:
 * - Does not work as long as submodules are not initialized.
 * - Does not check branch / tag.
 */
class GitDirtyTreeSensorPlugin extends SensorPluginBase implements ExtendedInfoSensorPluginInterface {

  /**
   * The executed command output.
   *
   * @var array
   */
  protected $cmdOutput;

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    // For security reasons, some hostings disable exec() related functions.
    // Since they fail silently, we challenge exec() and check if the result
    // matches the expectations.
    if (exec('echo "enabled"') != 'enabled') {
      $result->addStatusMessage('The function exec() is disabled. You need to enable it.');
      $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
      return;
    }

    $exit_code = 0;
    exec($this->buildCMD(), $this->cmdOutput, $exit_code);
    if ($exit_code > 0) {
      $result->addStatusMessage('Non-zero exit code ' . $exit_code);
      $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
    }

    $result->setExpectedValue(0);

    if (!empty($this->cmdOutput)) {
      $result->setValue(count($this->cmdOutput));
      $result->addStatusMessage('Files in unexpected state: ' . $this->getShortFileList($this->cmdOutput, 2));
      $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
    }
    else {
      $result->setValue(0);
      $result->addStatusMessage('Git repository clean');
      $result->setStatus(SensorResultInterface::STATUS_OK);
    }
  }

  /**
   * Returns a shortened file list for the status message.
   *
   * @param string $input
   *   Result from running the git command.
   *
   * @param int $max_files
   *   Limit the number of files returned.
   *
   * @param int $max_length
   *   Limit the length of the path to the file
   *
   * @return string
   *   File names from $output.
   */
  protected function getShortFileList($input, $max_files = 2, $max_length = 50) {
    $output = array();
    // Remove unnecessary whitespace.
    foreach (array_slice($input, 0, $max_files) as $line) {
      // Separate type of modification and path to file.
      $parts = explode(' ', $line, 2);
      if (strlen($parts[1]) > $max_length) {
        // Put together type of modification and path to file limited by
        // $pathLength.
        $output[] = $parts[0] . ' …' . substr($parts[1], -$max_length);
      }
      else {
        // Return whole line if path is shorter then $pathLength.
        $output[] = $line;
      }
    }
    return implode(', ', $output);
  }


  /**
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {
    $output = [];

    $output['cmd'] = array(
      '#type' => 'item',
      '#title' => t('Command'),
      '#markup' => $this->buildCMD(),
    );
    $output['output'] = array(
      '#type' => 'item',
      '#title' => t('Output'),
      '#markup' => '<pre>' . implode("\n", $this->cmdOutput) . '</pre>',
    );

    return $output;
  }

  /**
   * Build the command to be passed into shell_exec().
   *
   * @return string
   *   Shell command.
   */
  protected function buildCMD() {
    $repo_path = DRUPAL_ROOT . '/' . $this->sensorConfig->getSetting('repo_path');
    $cmd = $this->sensorConfig->getSetting('cmd');
    return 'cd ' . escapeshellarg($repo_path) . "\n$cmd  2>&1";
  }
}
