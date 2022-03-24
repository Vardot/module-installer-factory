<?php

namespace Vardot\Installer;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Module Installer Factory.
 *
 */
class ModuleInstallerFactory {

  /**
   * Install a list of modules inside [$moduleName].info.yml
   *
   * @param string $moduleName
   * @param string $modulesListKey
   * @param bool $setModuleWeight
   * @return void
   */
  public static function installList(string $moduleName, string $modulesListKey = 'install', $setModuleWeight = TRUE) {
    $modulePath = \Drupal::service('module_handler')->getModule($moduleName)->getPath();

    $moduleInfoFile = $modulePath . '/' . $moduleName . '.info.yml';
    if (file_exists($moduleInfoFile)) {
      $module_info_data = (array) Yaml::parse(file_get_contents($moduleInfoFile));
      if (
        isset($module_info_data[$modulesListKey])
        && is_array($module_info_data[$modulesListKey])
      ) {
        foreach ($module_info_data[$modulesListKey] as $module) {
          if (!\Drupal::moduleHandler()->moduleExists($module)) {
            \Drupal::service('module_installer')->install([$module], TRUE);
          }
        }

        self::setModuleWeightAfterInstallation($moduleName);
      }
    }
  }

  public static function setModuleWeightAfterInstallation(string $moduleName, string $modulesListKey = 'install', $modules = []) {
    if (empty($modules)) {
      $modulePath = \Drupal::service('module_handler')->getModule($moduleName)->getPath();
      $moduleInfoFile = $modulePath . '/' . $moduleName . '.info.yml';

      if (file_exists($moduleInfoFile)) {
        $infoFileData = (array) Yaml::parse(file_get_contents($moduleInfoFile));
        $modules = $infoFileData[$modulesListKey];
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

    $newWeight = max($modulesWeight) + 1;

    if (function_exists('module_set_weight')) {
      module_set_weight($moduleName, $newWeight);
    }
  }

  /**
   * Import configuration from scaned directory.
   *
   * @param string $moduleName
   * @param string $mask
   * @param string $configDirectory
   * @return void
   */
  public static function importConfigsFromScanedDirectory(string $moduleName, string $mask, string $configDirectory = InstallStorage::CONFIG_OPTIONAL_DIRECTORY) {
    $modulePath = \Drupal::service('module_handler')->getModule($moduleName)->getPath();
    $configPath = "{$modulePath}/{$configDirectory}";

    if (is_dir($configPath)) {
      \Drupal::service('config.installer')->installDefaultConfig('module', $moduleName);

      // Install any optional config the module provides.
      $storage = new FileStorage($configPath, StorageInterface::DEFAULT_COLLECTION);
      \Drupal::service('config.installer')->installOptionalConfig($storage, '');

      // Create field storage configs first in active config.
      $configFiles = \Drupal::service('file_system')->scanDirectory($configPath, $mask);
      if (isset($configFiles)  && is_array($configFiles)) {
        foreach ($configFiles as $configFile) {
          $configFileContent = file_get_contents(DRUPAL_ROOT . '/' . $configFile->uri);
          $configFileData = (array) Yaml::parse($configFileContent);
          $configFactory = \Drupal::service('config.factory')->getEditable($configFile->name);
          $configFactory->setData($configFileData)->save(TRUE);
        }
      }
    }
  }
}
