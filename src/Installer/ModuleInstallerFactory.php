<?php

namespace Vardot\Installer;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\user\Entity\Role;

/**
 * Module Installer Factory.
 *
 */
class ModuleInstallerFactory {

  /**
   * Install a list of modules inside [$moduleName].info.yml
   *
   * @param string $moduleName
   *   The machine name for the module.
   * @param string $modulesListKey
   *   Optional list key which to get the list of modules from. Default 'install'.
   * @param bool $setModuleWeight
   *   A flag to auto set the weight of the module after installation of list of modules.
   * @return void
   */
  public static function installList(string $moduleName, string $modulesListKey = 'install', $setModuleWeight = TRUE) {
    $modulePath = \Drupal::service('module_handler')->getModule($moduleName)->getPath();
    $moduleInfoFile = $modulePath . '/' . $moduleName . '.info.yml';

    if (file_exists($moduleInfoFile)) {
      $moduleInfoFileContent = file_get_contents($moduleInfoFile);
      $moduleInfoData = (array) Yaml::parse($moduleInfoFileContent);
      if (isset($moduleInfoData[$modulesListKey])
          && is_array($moduleInfoData[$modulesListKey])) {

        foreach ($moduleInfoData[$modulesListKey] as $module) {
          if (!\Drupal::moduleHandler()->moduleExists($module)) {
            \Drupal::service('module_installer')->install([$module], TRUE);
          }
        }

        if ($setModuleWeight) {
          self::setModuleWeightAfterInstallation($moduleName);
        }
      }
    }
  }

  /**
   * Set the weight of the module after installation of list of modules.
   * 
   * To make sure that any hook or event subscriber workers after all used modules.
   *
   * @param string $moduleName
   *   The machine name for the module.
   * @param string $modulesListKey
   *   Optional list key which to get the list of modules from. Default 'install'.
   * @param array $modules
   *   Optional list of modules in an array.
   * @return void
   */
  public static function setModuleWeightAfterInstallation(string $moduleName, string $modulesListKey = 'install', array $modules = []) {
    if (empty($modules)) {
      $modulePath = \Drupal::service('module_handler')->getModule($moduleName)->getPath();
      $moduleInfoFile = $modulePath . '/' . $moduleName . '.info.yml';

      if (file_exists($moduleInfoFile)) {
        $moduleInfoFileContent = file_get_contents($moduleInfoFile);
        $infoFileData = (array) Yaml::parse($moduleInfoFileContent);
        if (isset($infoFileData[$modulesListKey])) {
          $modules = $infoFileData[$modulesListKey];
        }
      }
    }

    $installedModules = (array) \Drupal::service('config.factory')->getEditable('core.extension')->get('module');
    $modulesWeight = [];

    foreach ($installedModules as $module => $weight) {
      foreach ($modules as $key => $name) {
        if ($module === $name) {
          array_push($modulesWeight, $weight);
        }
      }
    }

    if (count($modulesWeight) > 0) {
      $newWeight = max($modulesWeight) + 1;

      if (function_exists('module_set_weight')) {
        module_set_weight($moduleName, $newWeight);
      }
    }
  }

  /**
   * Import configuration from scaned directory.
   *
   * @param string $moduleName
   *   The machine name for the module.
   * @param string $mask
   *   The mask regular expression format to scan with.
   * @param string $configDirectory
   *   The config directory which to partial import using the
   *   mask regular expression format.
   * @return void
   */
  public static function importConfigsFromScanedDirectory(string $moduleName, string $mask, string $configDirectory = InstallStorage::CONFIG_OPTIONAL_DIRECTORY) {
    $modulePath = \Drupal::service('module_handler')->getModule($moduleName)->getPath();
    $configDirectoryPath = $modulePath . '/' . $configDirectory;

    if (is_dir($configDirectoryPath)) {
      \Drupal::service('config.installer')->installDefaultConfig('module', $moduleName);

      // Install any optional config the module provides.
      $storage = new FileStorage($configDirectoryPath, StorageInterface::DEFAULT_COLLECTION);
      \Drupal::service('config.installer')->installOptionalConfig($storage, '');

      // Create field storage configs first in active config.
      $configFiles = \Drupal::service('file_system')->scanDirectory($configDirectoryPath, $mask);
      if (isset($configFiles)  && is_array($configFiles)) {
        foreach ($configFiles as $configFile) {
          $configImportFile = DRUPAL_ROOT . '/' . $configFile->uri;
          if (file_exists($configImportFile)) {
            $configFileContent = file_get_contents($configImportFile);
            $configFileData = (array) Yaml::parse($configFileContent);
            $configFactory = \Drupal::service('config.factory')->getEditable($configFile->name);
            $configFactory->setData($configFileData)->save(TRUE);
          }
        }
      }
    }
  }

  /**
   * Import configuration from array list of config files.
   *
   * @param string $moduleName
   *   The machine name for the module.
   * @param array $listOfConfigFiles
   *   The list of config files.
   * @param string $configDirectory
   *   The config directory which to partial import the list from.
   * @return void
   */
  public static function importConfigsFromList(string $moduleName, array $listOfConfigFiles, string $configDirectory = InstallStorage::CONFIG_OPTIONAL_DIRECTORY) {
    $modulePath = \Drupal::service('module_handler')->getModule($moduleName)->getPath();
    $configDirectoryPath = $modulePath . '/' . $configDirectory;

    if (is_dir($configDirectoryPath)) {
      foreach ($listOfConfigFiles as $configName) {
        $configFile = $configDirectoryPath . '/' . $configName . '.yml';
        if (file_exists($configFile)) {
          $configContent = file_get_contents($configFile);
          $configData = (array) Yaml::parse($configContent);
          $configFactory = \Drupal::configFactory()->getEditable($configName);
          $configFactory->setData($configData)->save(TRUE);
        }
      }
    }
  }

  /**
   * Add Permissions for user roles from scanned directory.
   *
   * @param string $moduleName
   *   The machine name for the module.
   * @param string $permissionsDirectory
   *   The permissions directory.
   * @return void
   */
  public static function addPermissions(string $moduleName, string $permissionsDirectory = 'config/permissions') {
    $modulePath = \Drupal::service('module_handler')->getModule($moduleName)->getPath();
    $permissionsDirectoryPath = $modulePath . '/' . $permissionsDirectory;

    if (is_dir($permissionsDirectoryPath)) {
      // Scan all permissions in the "config/permissions" folder.
      $permissionFiles = \Drupal::service('file_system')->scanDirectory($permissionsDirectoryPath, '/.*/');
      if (isset($permissionFiles) && is_array($permissionFiles)) {

        $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();

        foreach ($permissionFiles as $permissionFile) {
          $permissionImportFile = DRUPAL_ROOT . '/' . $permissionFile->uri;
          if (file_exists($permissionImportFile)) {
            $permissionFileContent = file_get_contents($permissionImportFile);
            $permissionFileData = (array) Yaml::parse($permissionFileContent);

            if (isset($permissionFileData['id'])
              && is_string($permissionFileData['id'])
              && $permissionFileData['id'] != ''
              && isset($roles[$permissionFileData['id']])
              && isset($permissionFileData['permissions'])
              && is_array($permissionFileData['permissions'])
              && count($permissionFileData['permissions']) > 0) {

              foreach($permissionFileData['permissions'] as $permission) {
                $roles[$permissionFileData['id']]->grantPermission($permission);
              }

              $roles[$permissionFileData['id']]->save();
            }
          }
        }
      }
    }
  }

  /**
   * Remove non-existent permissions.
   *
   * Developers are facing this issue while uninstalling a module with dynamic permissions.
   * Cloned the ready function for Calculate role dependencies and remove non-existent permissions.
   * https://git.drupalcode.org/project/drupal/-/blob/9.5.11/core/modules/user/user.post_update.php?ref_type=tags#L22
   * Which was removed in Drupal 10 and deprecated in Drupal 9
   * Use when upgrading with missing static or dynamic permissions.
   */
  public static function removeNoneExistentPermissions(array &$sandbox = []) {
    $cleaned_roles = [];
    $existing_permissions = array_keys(\Drupal::service('user.permissions')
      ->getPermissions());
    \Drupal::classResolver(ConfigEntityUpdater::class)
      ->update($sandbox, 'user_role', function (Role $role) use ($existing_permissions, &$cleaned_roles) {
        $removed_permissions = array_diff($role->getPermissions(), $existing_permissions);
        if (!empty($removed_permissions)) {
          $cleaned_roles[] = $role->label();
          \Drupal::logger('update')->notice(
            'The role %role has had the following non-existent permission(s) removed: %permissions.',
            [
              '%role' => $role->label(),
              '%permissions' => implode(', ', $removed_permissions),
            ]
          );
          $permissions = array_intersect($role->getPermissions(), $existing_permissions);
          $role->set('permissions', $permissions);
          return TRUE;
        }
      });

    if (!empty($cleaned_roles)) {
      return new PluralTranslatableMarkup(
        count($cleaned_roles),
        'The role %role_list has had non-existent permissions removed. Check the logs for details.',
        'The roles %role_list have had non-existent permissions removed. Check the logs for details.',
        ['%role_list' => implode(', ', $cleaned_roles)]
      );
    }

  }

}
