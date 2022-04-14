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
}
