# Module Installer Factory


Provides developers with a class to perform custom processer for `install:` in `module_name.info.yml` file.

Let us have the `module_name.info.yml` with the following items for example

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


### 1. Require the Package in Your Module or Project

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
  ModuleInstallerFactory::install('mdoule_name');
  ModuleInstallerFactory::setModuleWeightAfterInstallation('mdoule_name');
```

