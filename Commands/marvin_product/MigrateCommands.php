<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Commands\marvin\CommandsBase;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Robo\Collection\CollectionBuilder;
use Robo\State\Data as RoboStateData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * @todo Validate migration group name.
 */
class MigrateCommands extends CommandsBase implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * @hook option site:install
   */
  public function onOptionSiteInstall(Command $command, AnnotationData $annotationData) {
    if (!$command->getDefinition()->hasOption('marvin-migrate')) {
      $command->addOption(
        'marvin-migrate',
        '',
        InputOption::VALUE_OPTIONAL,
        'Name of the Marvin migration group (drush.yml#marvin.migrate.*) to import after site install and config import. This option has only effect when the --existing-config is on.'
      );
    }
  }

  /**
   * @hook post-command site:install
   *
   * @todo Validate $groupName.
   */
  public function onPostSiteInstall($parentResult, CommandData $commandData) {
    $logger = $this->getLogger();
    $input = $commandData->input();

    $withExistingConfig = !empty($input->getOption('existing-config'));
    $migrationGroupName = $input->getOption('marvin-migrate');

    if (!$withExistingConfig) {
      if ($migrationGroupName) {
        $logger->notice("The '$migrationGroupName' content migration is skipped, because the config wasn't imported.");
      }

      return;
    }

    if (!$migrationGroupName) {
      if ($migrationGroupName === '') {
        $logger->notice("The content migration intentionally skipped.");
      }

      return;
    }

    $migrateResult = $this
      ->getTaskMigrateImport($migrationGroupName)
      ->run();

    if ($migrateResult->wasSuccessful()) {
      return;
    }

    $logger->error('Default content migration failed');
  }

  /**
   * This is a shortcut for `drush migrate:import ...`.
   *
   * The $groupName doesn't come from "migrate_plus.migration_group.*.yml"
   * config.
   * This kind of $groupName has to be defined in a "drush.yml" file,
   * under the "marvin.migrate.*" key.
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
