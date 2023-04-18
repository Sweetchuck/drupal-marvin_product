<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\marvin\Robo\SymlinkUpsertTaskLoader;
use Drush\Attributes as CLI;
use Drush\Commands\marvin\CommandsBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RuntimeEnvironmentCommands extends CommandsBase {

  use SymlinkUpsertTaskLoader;

  #[CLI\Hook(
    type: HookManager::ON_EVENT,
    target: 'marvin:runtime-environment:switch',
  )]
  public function onEventMarvinRuntimeEnvironmentSwitch(
    InputInterface $input,
    OutputInterface $output,
    array $rte,
  ): array {
    return [
      'marvin_product.drush_local_yml' => [
        'task' => $this
          ->taskMarvinSymlinkUpsert()
          ->setSymlinkName('drush/drush.local.yml')
          ->setSymlinkSrc("drush/drush.{$rte['id']}.yml")
          ->setSymlinkDst("./drush.{$rte['id']}.yml"),
      ],
    ];
  }

}
