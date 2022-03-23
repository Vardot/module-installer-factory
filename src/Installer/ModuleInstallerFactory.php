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
  public static function installList(string $moduleName, string $modulesListKey = "install", $setModuleWeight = TRUE) {
    $modulePath = \Drupal::service('module_handler')->getModule($moduleName)->getPath();

    // Processer for install: in [$module_name].info.yml file.
    // ------------------------------------------------------------------------.
    $moduleInfoFile = "{$modulePath}/{$moduleName}.info.yml";
    if (file_exists($moduleInfoFile)) {
      $moduleInfoData = (array) Yaml::parse(file_get_contents($moduleInfoFile));
      $moduleInstallData = $moduleInfoData[$modulesListKey];
      if (isset($moduleInstallData) && is_array($moduleInstallData)) {
        foreach ($moduleInstallData as $module) {
          if (\Drupal::service('module_handler')->moduleExists($module)) {
            \Drupal::service('module_installer')->install([$module], TRUE);
          }
        }
      }
    }

    if ($setModuleWeight) {
      self::setModuleWeightAfterInstallation($moduleName, $modulesListKey);
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

  /**
   * Get module weight and set it at the end of the list of modules installed by it.
   *
   * @param string $moduleName
   * @param string $modulesListKey
   * @param array $modules
   * @return void
   */
  public static function setModuleWeightAfterInstallation(string $moduleName, string $modulesListKey = "install", array $modules = []) {
    // get all modules from core.extension
    $installedModules = (array) \Drupal::service('config.factory')->getEditable('core.extension')->get('module');

    if (count($modules) > 0) {

      // empty array to store all modules weight [$modules]
      $modulesWeight = [];

      // Loop over all the installed modules and in modules added using this function [array $modules].
      // And get the weight of all modules inside [array $modules], then store it in $modulesWeight.
      foreach ($installedModules as $module => $weight) {
        foreach ($modules as $moduleKey => $module_name) {
          if ($module === $module_name) {
            $modulesWeight += [$module => $weight];
          }
        }
      }

      // Get max weight of modules installed by module.
      $maxWeight = max($modulesWeight);

      // Set [$moduleName] weight to be grater than higher one by 1.
      module_set_weight($moduleName, $maxWeight + 1);
    }

    if(count($modules) === 0) {
      $modulePath = \Drupal::service('module_handler')->getModule($moduleName)->getPath();
      $moduleInfoFile = "{$modulePath}/{$moduleName}.info.yml";

      if (file_exists($moduleInfoFile)) {
        $infoFileData = (array) Yaml::parse(file_get_contents($moduleInfoFile));
        $modules = $infoFileData[$modulesListKey];

        $modulesWeight = [];

        // Loop over all the installed modules and in modules added using this function [array $modules].
        // And get the weight of all modules inside [array $modules], then store it in $modulesWeight.
        foreach ($installedModules as $module => $weight) {
          foreach ($modules as $moduleKey => $module_name) {
            if ($module === $module_name) {
              $modulesWeight += [$module => $weight];
            }
          }
        }

        // Get max weight of modules installed by module.
        $maxWeight = max($modulesWeight);

        // Set [$moduleName] weight to be grater than higher one by 1.
        module_set_weight($moduleName, $maxWeight + 1);
      }
    }
  }
}

