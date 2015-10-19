<?php
  /**
   * @file
   * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\UserIntegritySensorPlugin.
   */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\SensorPlugin\ExtendedInfoSensorPluginInterface;
use Drupal\monitoring\SensorPlugin\SensorPluginBase;
use Drupal\user\Entity\User;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\user\Entity\Role;

/**
 * Monitors user data changes.
 *
 * A custom database query is used here instead of entity manager for
 * performance reasons.
 *
 * @SensorPlugin(
 *   id = "user_integrity",
 *   label = @Translation("Privileged user integrity"),
 *   description = @Translation("Monitors name and e-mail changes of users with access to restricted permissions. Checks if authenticated or anonymous users have privileged access."),
 *   addable = FALSE
 * )
 */
class UserIntegritySensorPlugin extends SensorPluginBase implements ExtendedInfoSensorPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $configurableValueType = FALSE;

  /**
   * The max number of users to list in the verbose output.
   *
   * @var int
   */
  protected $listSize = 500;

  /**
   * The list of restricted permissions.
   *
   * @var array
   */
  protected $restrictedPermissions = array();

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $sensor_result) {

    // Get role IDs with restricted permissions.
    $role_ids = $this->getRestrictedRoles();

    $current_users = $this->processUsers($this->loadCurrentUsers($role_ids));
    // Add sensor message, count of current privileged users.
    $sensor_result->addStatusMessage(count($current_users) . ' privileged user(s)');
    $sensor_result->setStatus(SensorResultInterface::STATUS_OK);
    // Load old user data.
    $old_users = \Drupal::keyValue('monitoring.users')->getAll();
    // If user data is not stored, store them first.
    if (empty($old_users)) {
      foreach ($current_users as $user) {
        \Drupal::keyValue('monitoring.users')->set($user['id'], $user);
      }
    }
    // Check for new users and changes in existing users.
    else {
      $new_users = array_diff_key($current_users, $old_users);
      if (!empty($new_users)) {
        $sensor_result->setStatus(SensorResultInterface::STATUS_WARNING);
        $sensor_result->addStatusMessage(count($new_users) . ' new user(s)');
      }
      // Get the count of privileged users with changes.
      $count = 0;
      $changed_users_ids = array_intersect(array_keys($current_users), array_keys($old_users));
      foreach ($changed_users_ids as $changed_user_id) {
        $changed = $this->getUserChanges($current_users[$changed_user_id], $old_users[$changed_user_id]);
        if (!empty($changed)) {
          $count++;
        }
      }
      if ($count > 0) {
        $sensor_result->addStatusMessage($count . ' changed user(s)');
        $sensor_result->setStatus(SensorResultInterface::STATUS_WARNING);
      }
    }

    // Check anonymous and authenticated users with restricted permissions and
    // show a message.
    $user_register = \Drupal::config('user.settings')->get('register');

    // Check if authenticated or anonymous users have restrict access perms.
    $role_ids_after = array_intersect($role_ids, ['authenticated', 'anonymous']);
    $role_labels = [];
    foreach (Role::loadMultiple($role_ids_after) as $role) {
      $role_labels[$role->id()] = $role->label();
    }

    if (!empty($role_labels)) {
      $sensor_result->addStatusMessage('Privileged access for roles @roles', array('@roles' => implode(', ', $role_labels)));
      $sensor_result->setStatus(SensorResultInterface::STATUS_WARNING);
    }

    // Further escalate if the restricted access is for anonymous.
    if ((in_array('anonymous', $role_ids))) {
      $sensor_result->setStatus(SensorResultInterface::STATUS_CRITICAL);
    }

    // Check if self registration is possible.
    if ((in_array('authenticated', $role_ids)) && $user_register != USER_REGISTER_ADMINISTRATORS_ONLY) {
      $sensor_result->addStatusMessage('Self registration possible.');
      $sensor_result->setStatus(SensorResultInterface::STATUS_CRITICAL);
    }
  }

  /**
   * Gets a list of available users.
   *
   * @param string[] $role_ids
   *   Roles to filter users.
   *
   * @return \Drupal\user\Entity\User[]
   *   Available users.
   */
  protected function loadCurrentUsers(array $role_ids) {
    if (!$role_ids) {
      return [];
    }

    // Loading all users and managing them will kill the system so we limit
    // them.
    $query = \Drupal::entityQuery('user')
      ->sort('access', 'DESC')
      ->range(0, $this->listSize);

    // The authenticated role is not persisted and it could have restrict access
    // so we load every user.
    if (in_array('authenticated', $role_ids)) {
      $uids = $query->condition('uid', '0', '<>')
        ->execute();
    }
    else {
      // Load all users with the roles.
      $uids = $query->condition('roles', $role_ids, 'IN')
        ->execute();
    }

    return User::loadMultiple($uids);
  }

  /**
   * Gets changes made to user data.
   *
   * @param array $current_values
   *   Current user data returned by ::processUsers().
   * @param array $expected_values
   *   Expected user data returned by ::processUsers().
   *
   * @return string[][]
   *   Changes in user.
   */
  protected function getUserChanges(array $current_values, array $expected_values) {

    $changes = array();

    if ($current_values['name'] != $expected_values['name']) {
      $changes['name']['expected_value'] = $expected_values['name'];
      $changes['name']['current_value'] = $current_values['name'];

    }
    if ($current_values['mail'] != $expected_values['mail']) {
      $changes['mail']['expected_value'] = $expected_values['mail'];
      $changes['mail']['current_value'] = $current_values['mail'];
    }

    if ($current_values['password'] != $expected_values['password']) {
      $changes['password']['expected_value'] = '';
      $changes['password']['current_value'] = t('Password changed');
    }
    return $changes;
  }

  /**
   * Gets a list of restricted roles.
   *
   * @return string[]
   *   Restricted roles.
   */
  protected function getRestrictedRoles() {
    /** @var \Drupal\user\PermissionHandlerInterface $permission_handler */
    $permission_handler = \Drupal::service('user.permissions');
    $available_permissions = $permission_handler->getPermissions();;
    $this->restrictedPermissions = array();
    foreach ($available_permissions as $key => $value) {
      if (!empty($value['restrict access'])) {
        $this->restrictedPermissions[] = $key;
      }
    }
    $avaliable_roles = Role::loadMultiple();
    $restricted_roles = array();
    foreach ($avaliable_roles as $role) {
      $permissions = $role->getPermissions();
      if ($role->isAdmin() ||  array_intersect($permissions, $this->restrictedPermissions)) {
        $restricted_roles[] = $role->id();
      }
    }
    return $restricted_roles;
  }

  /**
   * Process user entity into raw value array.
   *
   * @param \Drupal\user\Entity\User[] $users
   *   Users to process.
   *
   * @return array
   *   Processed user data, list of arrays with keys id, name, mail, password
   *   ans changed time.
   */
  protected function processUsers(array $users) {
    $processed_users = array();
    foreach ($users as $user) {
      $id = $user->id();
      $processed_users[$id]['id'] = $id;
      $processed_users[$id]['name'] = $user->getUsername();
      $processed_users[$id]['mail'] = $user->getEmail();
      $processed_users[$id]['password'] = hash('sha256', $user->getPassword());
      $processed_users[$id]['changed'] = $user->getChangedTime();
      $processed_users[$id]['last_accessed'] = $user->getLastAccessedTime();
    }
    return $processed_users;
  }

  /**
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {

    $output = [];
    // Load all the old user data.
    $expected_users = \Drupal::keyValue('monitoring.users')->getAll();
    // Get available roles with restricted permission.
    $role_ids = $this->getRestrictedRoles();
    // Process the current user data.
    $current_users = $this->processUsers($this->loadCurrentUsers($role_ids));

    $new_users_id = array_diff(array_keys($current_users), array_keys($expected_users));
    $rows = [];

    // Create rows for new users.
    foreach ($new_users_id as $id) {
      $time_stamp = $current_users[$id]['changed'];
      $last_accessed = $current_users[$id]['last_accessed'];
      $user_name = array(
        '#theme' => 'username',
        '#account' => User::load($id),
      );
      $row['user'] = $user_name;
      $row['field'] = ['#markup' => ''];
      $row['current_value'] = ['#markup' => ''];
      $row['expected_value'] = ['#markup' => ''];

      $row['time'] = ['#markup' => date("Y-m-d H:i:s", $time_stamp)];
      $row['last_accessed'] = ['#markup' => $last_accessed != 0 ? date("Y-m-d H:i:s", $last_accessed) : 'never'];
      $rows[] = $row;
    }

    $old_user_ids = array_keys($expected_users);

    // Create rows for old users.
    foreach ($old_user_ids as $id) {
      $changes = $this->getUserChanges($current_users[$id], $expected_users[$id]);
      foreach ($changes as $key => $value) {
        $time_stamp = $current_users[$id]['changed'];
        $last_accessed = $current_users[$id]['last_accessed'];
        $user_name = array(
          '#theme' => 'username',
          '#account' => User::load($id),
        );
        $row['user'] = $user_name;
        $row['field'] = ['#markup' => $key];
        $row['current_value'] = ['#markup' => $value['current_value']];
        $row['expected_value'] = ['#markup' => $value['expected_value']];

        $row['time'] = ['#markup' => date("Y-m-d H:i:s", $time_stamp)];
        $row['last_accessed'] = ['#markup' => $last_accessed != 0 ? date("Y-m-d H:i:s", $last_accessed) : 'never'];
        $rows[] = $row;
      }
    }

    // Show query.
    $output['message'] = array(
      '#type' => 'item',
      '#title' => t('New and changed users with privileged access.'),
    );

    if (count($rows) > 0) {
      $header = [];
      $header['user'] = t('User');
      $header['Field'] = t('Field');
      $header['current_value'] = t('Current value');
      $header['expected_value'] = t('Expected value');
      $header['time'] = t('Changed on');
      $header['last_accessed'] = t('Last accessed');

      $output['users'] = array(
        '#type' => 'table',
        '#header' => $header,
      ) + $rows;
    }
    else {
      $output['result'] = [
        '#type' => 'item',
        '#markup' => t('No matching users were found.'),
      ];
    }

    // Show roles list with the permissions that are restricted for each.
    $roles_list = [];
    foreach (Role::loadMultiple($role_ids) as $role) {
      if (!$role->isAdmin()) {
        $restricted_permissions = array_intersect($this->restrictedPermissions, $role->getPermissions());
        $roles_list[] = $role->label() . ': ' . implode(", ", $restricted_permissions);
      }
    }
    $output['roles_list'] = array(
      '#type' => 'item',
      '#title' => t('List of roles with restricted permissions.'),
      '#markup' => implode('<br>', $roles_list),
    );
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

      $form['reset_users'] = array(
          '#type' => 'submit',
          '#value' => t('Reset user data'),
          '#submit' => array(array($this, 'submitConfirmPrivilegedUsers')),
        );
      return $form;
  }

 /**
   * Resets current keyValue storage.
   *
   * @return array
   *   Available users.
   */
  public function submitConfirmPrivilegedUsers(array $form, FormStateInterface $form_state) {
      \Drupal::keyValue('monitoring.users')->deleteAll();
      return $form;
  }

}
