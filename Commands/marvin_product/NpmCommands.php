<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\marvin\NpmCommandsBase;
use Robo\Collection\CollectionBuilder;
use Robo\State\Data as RoboStateData;

class NpmCommands extends NpmCommandsBase {

  #[CLI\Hook(
    type: HookManager::ON_EVENT,
    target: 'marvin:build',
  )]
  public function onEventMarvinBuild(): array {
    return [
      'marvin.build.npm' => [
        'weight' => -200,
        'task' => $this->getTaskNpmInstallPackage(
          $this->getComposerInfo()->name,
          $this->getProjectRootDir()
        ),
      ],
    ];
  }

  #[CLI\Hook(
    type: HookManager::ON_EVENT,
    target: 'marvin:git-hook:post-checkout',
  )]
  public function onEventMarvinGitHookPostCheckout(): array {
    return [
      'marvin:yarn-install' => [
        'weight' => 100,
        'task' => $this->getTaskPackageJsonNotification(),
      ],
    ];
  }

  /**
   * Builds frontend related codes.
   */
  #[CLI\Command(name: 'marvin:build:npm')]
  #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
  public function cmdMarvinBuildNpmExecute(): CollectionBuilder {
    return $this->getTaskNpmInstallPackage(
      $this->getComposerInfo()->name,
      $this->getProjectRootDir(),
    );
  }

  /**
   * @todo Write native Task.
   *
   * @see \Drush\Commands\marvin_product\ComposerCommands::getTaskComposerChangedNotification
   */
  protected function getTaskPackageJsonNotification(): \Closure {
    return function (RoboStateData $data): int {
      $fileNames = new \RegexIterator(
        new \ArrayIterator($data['changed.fileNames']),
        '@(^|/)(package\.json|yarn\.lock)$@'
      );

      $commands = [];
      foreach ($fileNames as $fileName) {
        $dirName = dirname($fileName) ?: '.';
        $commands[$dirName] = sprintf('cd %s && yarn install', escapeshellarg($dirName));
      }

      if (!$commands) {
        return 0;
      }

      $message = implode(PHP_EOL, [
        'One of the package.json or yarn.lock has been changed.',
        'You have to run the following commands:',
        '{commands}',
      ]);
      $context = [
        'commands' => implode(PHP_EOL, $commands),
      ];

      $this->getLogger()->warning($message, $context);

      return 0;
    };
  }

}
