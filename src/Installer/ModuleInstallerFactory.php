<?php

namespace Vardot\Installer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\ModuleInstaller;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\ConfigInstaller;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Module Installer Factory.
 *
 */
class ModuleInstallerFactory {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected static $configFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected static $moduleHandler;

  /**
   * The module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleInstaller
   */
  protected static $moduleInstaller;

  /**
   * The config installer seervice.
   *
   * @var \Drupal\Core\Config\ConfigInstaller
   */
  protected static $configInstaller;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected static $fileSystem;

  /**
   * ModuleInstallerFactory constructor.
   *
   * @param ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param ModuleHandler $moduleHandler
   *   The module handler service.
   * @param ModuleInstaller $moduleInstaller
   *   The module installer service.
   * @param ConfigInstaller $configInstaller
   *   The config installer service.
   * @param FileSystem $fileSystem
   *   The file system service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    ModuleHandler $moduleHandler,
    ModuleInstaller $moduleInstaller,
    ConfigInstaller $configInstaller,
    FileSystem $fileSystem)
  {
    self::$configFactory = $configFactory;
    self::$moduleHandler = $moduleHandler;
    self::$moduleInstaller = $moduleInstaller;
    self::$configInstaller = $configInstaller;
    self::$fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('module_installer'),
      $container->get('config.installer'),
      $container->get('file_system'),
    );
  }

  /**
   * Install a list of modules inside [$moduleName].info.yml
   *
   * @param string $moduleName
   * @param string $modulesList
   * @param bool $setModuleWeight
   * @return void
   */
  public static function installList(string $moduleName, string $modulesList, $setModuleWeight = TRUE) {
    $modulePath = self::$moduleHandler->getModule($moduleName)->getPath();

    // Processer for install: in [$module_name].info.yml file.
    // ------------------------------------------------------------------------.
    $moduleInfoFile = "{$modulePath}/{$moduleName}.info.yml";
    if (file_exists($moduleInfoFile)) {
      $moduleInfoData = (array) Yaml::parse(file_get_contents($moduleInfoFile));
      $moduleInstallData = $moduleInfoData[$modulesList];
      if (isset($moduleInstallData) && is_array($moduleInstallData)) {
        foreach ($moduleInstallData as $module) {
          if (self::$moduleHandler->moduleExists($module)) {
            self::$moduleInstaller->install([$module], TRUE);
          }
        }
      }
    }

    if ($setModuleWeight) {
      self::setModuleWeightAfterInstallation($moduleName, $modulesList);
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
    $modulePath = self::$moduleHandler->getModule($moduleName)->getPath();
    $configPath = "{$modulePath}/{$configDirectory}";

    if (is_dir($configPath)) {
      self::$configInstaller->installDefaultConfig('module', $moduleName);

      // Install any optional config the module provides.
      $storage = new FileStorage($configPath, StorageInterface::DEFAULT_COLLECTION);
      self::$configInstaller->installOptionalConfig($storage, '');

      // Create field storage configs first in active config.
      $configFiles = self::$fileSystem->scanDirectory($configPath, $mask);
      if (isset($configFiles)  && is_array($configFiles)) {
        foreach ($configFiles as $configFile) {
          $configFileContent = file_get_contents(DRUPAL_ROOT . '/' . $configFile->uri);
          $configFileData = (array) Yaml::parse($configFileContent);
          $configFactory = self::$configFactory->getEditable($configFile->name);
          $configFactory->setData($configFileData)->save(TRUE);
        }
      }
    }
  }

  /**
   * Get module weight and set it at the end of the list of modules installed by it.
   *
   * @param string $moduleName
   * @param array $modules
   * @return void
   */
  public static function setModuleWeightAfterInstallation(string $moduleName, string $modulesList, array $modules = []) {
    // get all modules from core.extension
    $installedModules = (array) self::$configFactory->getEditable('core.extension')->get('module');

    if (count($modules) > 0) {

      // empty array to put all modules weight [$modules]
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
      $modulePath = self::$moduleHandler->getModule($moduleName)->getPath();
      $moduleInfoFile = "{$modulePath}/{$moduleName}.info.yml";

      if (file_exists($moduleInfoFile)) {
        $infoFileData = (array) Yaml::parse(file_get_contents($moduleInfoFile));
        $modules = $infoFileData[$modulesList];

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

