<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\CommandsBase;
use Robo\Collection\CollectionBuilder;
use Robo\State\Data as RoboStateData;

class MigrateCommands extends CommandsBase {

  /**
   * @hook post-command site:install
   *
   * @todo Validate $groupName.
   */
  public function onPostSiteInstall() {
    $result = $this
      ->getTaskMigrateImport('default')
      ->run();

    if ($result->wasSuccessful()) {
      return;
    }

    $this->logger->error('Default content migration failed');
  }

  /**
   * This is a shortcut for `drupal migrate:import ...`.
   *
   * The $groupName doesn't come from "migrate_plus.migration_group.*.yml"
   * config.
   * This kind of $groupName has to be defined in a "drush.yml" file,
   * under the "command.marvin.settings.migrate.*" key.
   *
   * @see onPostSiteInstall
   *
   * @command marvin:migrate
   *
   * @usage drush marvin:migrate default
   *
   * @todo Validate $groupName.
   */
  public function migrateImport(string $groupName): CollectionBuilder {
    return $this->getTaskMigrateImport($groupName);
  }

  protected function getTaskMigrateImport(string $groupName): CollectionBuilder {
    return $this
      ->collectionBuilder()
      ->addCode($this->getTaskMigrateImportCollectModulesToEnable($groupName))
      ->addCode($this->getTaskMigrateImportEnableModules())
      ->addCode($this->getTaskMigrateImportDoIt($groupName))
      ->addCode($this->getTaskMigrateImportUninstallModules());
  }

  protected function getTaskMigrateImportCollectModulesToEnable(string $groupName): \Closure {
    return function (RoboStateData $data) use ($groupName): int {
      $configName = "command.marvin.settings.migrate.$groupName.module";
      $modulesToEnable = array_keys(
        $this->getConfig()->get($configName, []),
        TRUE,
        TRUE
      );

      try {
        $data['enabledModules'] = $this->getEnabledModules();
      }
      catch (\RuntimeException $e) {
        $this->getLogger()->error($e->getMessage());

        return 1;
      }

      $data['modulesToEnable'] = array_diff($modulesToEnable, $data['enabledModules']);

      $this->getLogger()->debug(
        'Modules to enable ({numOfModule}): {moduleNames}',
        [
          'numOfModule' => count($data['modulesToEnable']),
          'moduleNames' => implode(', ', $data['modulesToEnable']),
        ]
      );

      return 0;
    };
  }

  protected function getTaskMigrateImportEnableModules(): \Closure {
    return function (RoboStateData $data): int {
      if (empty($data['modulesToEnable'])) {
        $this->getLogger()->debug('There is no any module to enable.');

        return 0;
      }

      $response = drush_invoke_process(
        '@self',
        'pm:enable',
        $data['modulesToEnable']
      );

      if ($response === FALSE) {
        $this->getLogger()->error('pm:enable failed.');

        return 1;
      }

      return 0;
    };
  }

  protected function getTaskMigrateImportDoIt(string $groupName): \Closure {
    return function () use ($groupName): int {
      $cmdSiteAlias = '@self';
      $cmdName = 'migrate:import';
      $cmdArgs = [];
      $cmdOptions = [];

      $configNamePrefix = "command.marvin.settings.migrate.$groupName";
      $filters = ['group', 'tag'];
      foreach ($filters as $filter) {
        $values = (array) $this->getConfig()->get("$configNamePrefix.$filter", []);
        $values = array_keys($values, TRUE, TRUE);
        if ($values) {
          $cmdOptions[$filter] = implode(',', $values);
        }
      }

      if (!$cmdOptions) {
        $this->getLogger()->debug('`drush migrate:import` is skipped, because of the lack of filters.');

        return 0;
      }

      $response = drush_invoke_process($cmdSiteAlias, $cmdName, $cmdArgs, $cmdOptions);
      if ($response === FALSE) {
        $this->getLogger()->error('migrate:import failed.');

        return 1;
      }

      return 0;
    };
  }

  protected function getTaskMigrateImportUninstallModules(): \Closure {
    return function (RoboStateData $data): int {
      $logger = $this->getLogger();
      $modulesToUninstall = array_diff($this->getEnabledModules(), $data['enabledModules']);
      if (!$modulesToUninstall) {
        $logger->debug('There is no any module to uninstall.');

        return 0;
      }

      $response = drush_invoke_process(
        '@self',
        'pm:uninstall',
        $modulesToUninstall
      );

      if ($response === FALSE) {
        $logger->error('pm:uninstall failed.');

        return 1;
      }

      return 0;
    };
  }

  protected function getEnabledModules(): array {
    $response = drush_invoke_process(
      '@self',
      'pm:list',
      [],
      [
        'status' => 'enabled',
        'field' => 'name',
      ]
    );

    if ($response === FALSE) {
      throw new \RuntimeException('@todo Better error message');
    }

    return array_keys($response['object']);
  }

}
