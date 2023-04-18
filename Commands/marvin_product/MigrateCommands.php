<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\marvin\CommandsBase;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Robo\Collection\CollectionBuilder;
use Robo\State\Data as RoboStateData;

/**
 * @todo Validate migration group name.
 */
class MigrateCommands extends CommandsBase implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * This is a shortcut for `drush migrate:import ...`.
   *
   * The $groupName doesn't come from "migrate_plus.migration_group.*.yml"
   * config.
   * This kind of $groupName has to be defined in a "drush.yml" file,
   * under the "marvin.migrate.*" key.
   *
   * @todo Validate $groupName.
   *
   * @todo Remove these commands.
   * @todo The concept of "marvin.environment.modules" also not a good idea.
   */
  #[CLI\Command(name: 'marvin:migrate')]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  #[CLI\Argument(
    name: 'groupName',
    description: 'Name of the migration group defined in the "drush.yml#.marvin.migrate".',
  )]
  #[CLI\Usage(
    name: 'drush marvin:migrate default',
    description: 'Basic usage',
  )]
  public function cmdMigrateImportExecute(string $groupName): CollectionBuilder {
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
      $configName = "marvin.migrate.$groupName.module";
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

      $process = Drush::drush(
        $this->siteAliasManager()->getSelf(),
        'pm:enable',
        $data['modulesToEnable'],
        [
          'yes' => NULL,
        ]
      );
      $exitCode = $process
        ->setTimeout(NULL)
        ->run();

      if ($exitCode) {
        $this->getLogger()->error(
          "pm:enable failed.{nl}{command}{nl}{stdOutput}{nl}{stdError}",
          $this->logArgsFromProcess($process)
        );

        return 1;
      }

      return 0;
    };
  }

  protected function getTaskMigrateImportDoIt(string $groupName): \Closure {
    return function () use ($groupName): int {
      $cmdName = 'migrate:import';
      $cmdArgs = [];
      $cmdOptions = [];

      $configNamePrefix = "marvin.migrate.$groupName";
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

      $process = Drush::drush(
        $this->siteAliasManager()->getSelf(),
        $cmdName,
        $cmdArgs,
        $cmdOptions
      );
      $exitCode = $process
        ->setTimeout(NULL)
        ->run();

      if ($exitCode) {
        $this->getLogger()->error(
          'migrate:import failed.{nl}{command}{nl}{stdOutput}{nl}{stdError}',
          $this->logArgsFromProcess($process)
        );

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

      $process = Drush::drush(
        $this->siteAliasManager()->getSelf(),
        'pm:uninstall',
        $modulesToUninstall,
        [
          'yes' => NULL,
        ]
      );
      $exitCode = $process
        ->setTimeout(NULL)
        ->run();

      if ($exitCode) {
        $logger->error(
          'pm:uninstall failed.{nl}{command}{nl}{stdOutput}{nl}{stdError}',
          $this->logArgsFromProcess($process)
        );

        return 1;
      }

      return 0;
    };
  }

  /**
   * @return string[]
   */
  protected function getEnabledModules(): array {
    $process = Drush::drush(
      $this->siteAliasManager()->getSelf(),
      'pm:list',
      [],
      [
        'status' => 'enabled',
        'format' => 'json',
      ]
    );
    $exitCode = $process->run();

    if ($exitCode) {
      throw new \RuntimeException('to retrieve the enabled modules is failed');
    }

    return array_keys(json_decode($process->getOutput(), TRUE));
  }

}
