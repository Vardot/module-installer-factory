<?php

namespace Vardot\Installer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\ModuleInstaller;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\ConfigInstaller;
use Drupal\Core\DependencyInjection\ClassResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;
use Vardot\Entity\EntityDefinitionUpdateManager;

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
  protected $configFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleInstaller
   */
  protected $moduleInstaller;

  /**
   * The config installer seervice.
   *
   * @var \Drupal\Core\Config\ConfigInstaller
   */
  protected $configInstaller;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The class resolver service.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolver
   */
  protected $classResolver;

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
   * @param ClassResolver $classResolver
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    ModuleHandler $moduleHandler,
    ModuleInstaller $moduleInstaller,
    ConfigInstaller $configInstaller,
    FileSystem $fileSystem,
    ClassResolver $classResolver)
  {
    $this->configFactory = $configFactory;
    $this->moduleHandler = $moduleHandler;
    $this->moduleInstaller = $moduleInstaller;
    $this->configInstaller = $configInstaller;
    $this->fileSystem = $fileSystem;
    $this->classResolver = $classResolver;
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
      $container->get('class_resolver')
    );
  }

  /**
   * Applies all the detected valid changes.
   *
   * @param string $moduleName
   * @param bool $setModuleWeight
   */
  public function install(string $moduleName, $setModuleWeight = TRUE) {
    $modulePath = $this->getModulePath($moduleName);

    // Processer for install: in [$module_name].info.yml file.
    // ------------------------------------------------------------------------.
    $moduleInfoFile = "{$modulePath}/{$moduleName}.info.yml";
    if (file_exists($moduleInfoFile)) {
      $moduleInfoData = (array) Yaml::parse(file_get_contents($moduleInfoFile));
      $moduleInstallData = $moduleInfoData['install'];
      if (isset($moduleInstallData) && is_array($moduleInstallData)) {
        foreach ($moduleInstallData as $module) {
          if ($this->moduleHandler->moduleExists($module)) {
            $this->moduleInstaller->install([$module], TRUE);
          }
        }
      }
    }

    if ($setModuleWeight) {
      $this->setModuleWeightAfterInstallation($moduleName);
    }
  }

  public function installConfig($moduleName, string $configDirectory = "optional") {
    $modulePath = $this->getModulePath($moduleName);
    $installPath = str_replace("config/", "", $configDirectory);
    $configPath = "{$modulePath}/config/{$installPath}";

    if (is_dir($configPath)) {
      $this->configInstaller->installDefaultConfig('module', $moduleName);

      // Create field storage configs first in active config.
      $storageConfigFiles = $this->fileSystem->scanDirectory($configPath, '/^field.storage.*\\.(yml)$/i');
      if (isset($storageConfigFiles)  && is_array($storageConfigFiles)) {
        foreach ($storageConfigFiles as $storageConfigFile) {
          $storageConfigFileContent = file_get_contents(DRUPAL_ROOT . '/' . $storageConfigFile->uri);
          $storageConfigFileData = (array) Yaml::parse($storageConfigFileContent);
          $configFactory = $this->configFactory->getEditable($storageConfigFile->name);
          $configFactory->setData($storageConfigFileData)->save(TRUE);
        }
      }

      // Install any optional config the module provides.
      $storage = new FileStorage($configPath, StorageInterface::DEFAULT_COLLECTION);
      $this->configInstaller->installOptionalConfig($storage, '');

      // Have the .settings.yml configs into the active config.
      $settingsConfigFiles = $this->fileSystem->scanDirectory($configPath, '/^.*(settings.yml)$/i');
      if(isset($settingsConfigFiles) && is_array($settingsConfigFiles)) {
        foreach ($settingsConfigFiles as $settingsConfigFile) {
          $settingsConfigFileContent = file_get_contents(DRUPAL_ROOT . '/' . $settingsConfigFile->uri);
          $settingsConfigFileData = (array) Yaml::parse($settingsConfigFileContent);
          $configFactory = $this->configFactory->getEditable($settingsConfigFile->name);
          $configFactory->setData($settingsConfigFileData)->save(TRUE);
        }
      }
    }

    // ---------------------------------------------------------------------------
    // Entity updates to clear up any mismatched entity and/or field definitions
    // And Fix changes were detected in the entity type and field definitions.
    $this->classResolver
    ->getInstanceFromDefinition(EntityDefinitionUpdateManager::class)
    ->applyUpdates();

    // Have forced configs import after the entity and definitions updates.
    $forcedConfigsImportAfterEntityUpdates = [
      'core.entity_form_display.user.user.default',
    ];

    foreach ($forcedConfigsImportAfterEntityUpdates as $configName) {
      $configPath = "{$configPath}/{$configName}.yml";
      $configContent = file_get_contents($configPath);
      $configData = (array) Yaml::parse($configContent);
      $configFactory = $this->configFactory->getEditable($configName);
      $configFactory->setData($configData)->save(TRUE);
    }
  }

  /**
   * Get module weight and set it at the end of the list of modules installed by it.
   *
   * @param string $moduleName
   * @return void
   */
  function setModuleWeightAfterInstallation(string $moduleName) {
    // get all modules from core.extension
    $installed_modules = (array) $this->configFactory->getEditable('core.extension')->get('module');
    // get weight of varbase security
    $varbase_security_weight = $installed_modules['varbase_security'];
    // get all modules installed by varbase security
    $varbase_security_modules = (array) Yaml::parse(file_get_contents($moduleName))['install'];
    // empty array to put all modules weight [modules installed by varbase security]
    $varase_security_modules_weight = [];

    foreach ($installed_modules as $module => $weight) {
      foreach ($varbase_security_modules as $vs_module_key => $vs_module_name) {
        if ($module === $vs_module_name) {
          $varase_security_modules_weight += [$module => $weight];
        }
      }
    }

    // Get max weight of modules installed by varbase_security.
    $max_wight = max($varase_security_modules_weight);

    /**
     * Set varbase_security weight to be grater than higher one by 1 if it's
     * less than or equal to max.
     */
    if ($varbase_security_weight <= $max_wight) {
      module_set_weight('varbase_security', $max_wight + 1);
    }

    module_set_weight('varbase_security', $max_wight + 1);
  }

  /**
   * Get module path function
   *
   * @param string $moduleName
   * @return void
   */
  private function getModulePath(string $moduleName) {
    return $modulePath = $this->moduleHandler->getModule($moduleName)->getPath();
  }
}

