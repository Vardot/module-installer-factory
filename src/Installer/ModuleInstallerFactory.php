<?php

namespace Vardot\Installer;

use Symfony\Component\Yaml\Yaml;


/**
 * Module Installer Factory.
 *
 */
class ModuleInstallerFactory {

  /**
   * Applies all the detected valid changes.
   */
  public function install() {

  }

  /**
   * Get modules weight.
   *
   * @param string $fileName
   * @return void
   */
  function setModuleWeightAfterInstallation(string $fileName) {
    // get all modules from core.extension
    $installed_modules = (array) \Drupal::service('config.factory')->getEditable('core.extension')->get('module');
    // get weight of varbase security
    $varbase_security_weight = $installed_modules['varbase_security'];
    // get all modules installed by varbase security
    $varbase_security_modules = (array) Yaml::parse(file_get_contents($file_name))['install'];
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


}