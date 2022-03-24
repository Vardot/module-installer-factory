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
For example:

```
  ModuleInstallerFactory::installList('varbase_core', 'install', TRUE);
  ModuleInstallerFactory::importConfigsFromScanedDirectory('varbase_core', '/^field.storage.*\\.(yml)$/i', 'config/optional');
  ModuleInstallerFactory::setModuleWeightAfterInstallation('varbase_core', 'install', []);
```

