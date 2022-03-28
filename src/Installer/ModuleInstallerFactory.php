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

  /**
   * Set the weight of the module after installation of list of modules.
   * 
   * To make sure that any hook or event subscriber workes after all used modules.
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
    $configPath = "{$modulePath}/{$configDirectory}";

    if (is_dir($configPath)) {
      foreach ($listOfConfigFiles as $configName) {
        $configPath = $configPath . '/' . $configName . '.yml';
        $configContent = file_get_contents($configPath);
        $configData = (array) Yaml::parse($configContent);
        $configFactory = \Drupal::configFactory()->getEditable($configName);
        $configFactory->setData($configData)->save(TRUE);
      }
    }
  }
}
