# Module Installer Factory


Provides developers with a class for modules installer factory.

This class is working with `module_name.info.yml` file with any modules key.

Let us have the `module_name.info.yml` with the following items under `install` key for example:

```
name: "Module Name"
description: "Module description."
type: module
core_version_requirement: ^9
dependencies:
  - drupal:node
  - drupal:editor
  - drupal:ckeditor
  - drupal:filter
install:
  - extlink
  - linkit
  - anchor_link
```


## How to use Module Installer Factory Class

### 1. Require the Package in your root composer.json file

```
  "vardot/module-installer-factory": "~1.0"
```

### Or require the Package in your Project with a command

```
$ composer require vardot/module-installer-factory:~1.0
```

### 2. Add Needed Namespace

Add the following name space at in custom modules or custom installation profiles.

```
use Vardot\Installer\ModuleInstallerFactory;
```

### 3. Use the following methods in your custom install events

```
  ModuleInstallerFactory::installList('mdouleName', 'modulesListKey', TRUE);
  ModuleInstallerFactory::importConfigsFromScanedDirectory('moduleName', 'mask', 'configDirectory');
  ModuleInstallerFactory::setModuleWeightAfterInstallation('mdouleName', 'modulesListKey', []);
```

### Explanation about methods and its parameters:

* `installList('mdouleName', 'modulesListKey', TRUE)`:

This method is for installing a list of modules listed in `module_name.info.yml`.

params:

  * `'moduleName'`: a string represent the machine name of a module.
  * `'modulesListKey'`: a string represent the key of the modules list in `module_name.info.yml`, like `- install` in the example above, and by default it will be `install`.
  * `TRUE`: a flag to call `setModuleWeightAfterInstallation()` method.

* `importConfigsFromScanedDirectory('moduleName', 'mask', 'configDirectory')`:

This method is for installing configuration of the module in specific directory.

params:

  * `'moduleName'`: a string represent the machine name of a module.
  * `'mask'`: a string represents regex of a files extension.
  * `'configDirectory'`: the directory of configuration, and by default it will be `'config/optional'`.

* `setModuleWeightAfterInstallation('mdouleName', 'modulesListKey', [])`:

This method is for change the weight of the module to be grater than all modules in the list.

params:

  * `'moduleName'`: a string represent the machine name of a module.
  * `'modulesListKey'`: a string represent the key of the modules list in `module_name.info.yml`, like `- install` in the example above, and by default it will be `install`.
  * `array $modules`: this array will contain all modules names that you want to make your module weight grater that it.
