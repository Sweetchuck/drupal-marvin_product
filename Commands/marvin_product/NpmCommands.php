<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\Build\NpmCommandsBase;
use Robo\Collection\CollectionBuilder;

class NpmCommands extends NpmCommandsBase {

  /**
   * @hook on-event marvin:build
   */
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

  /**
   * @command marvin:build:npm
   * @bootstrap none
   */
  public function npmInstall(): CollectionBuilder {
    return $this->getTaskNpmInstallPackage(
      $this->getComposerInfo()->name,
      $this->getProjectRootDir()
    );
  }

}
