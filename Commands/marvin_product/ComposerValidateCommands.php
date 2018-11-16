<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\Lint\ComposerValidateCommandsBase;
use Robo\Collection\CollectionBuilder;

class ComposerValidateCommands extends ComposerValidateCommandsBase {

  /**
   * @hook on-event marvin:git-hook:pre-commit
   */
  public function onEventMarvinGitHookPreCommit(): array {
    return [
      'marvin:lint:composer-validate' => [
        'weight' => -201,
        'task' => $this->getTaskComposerValidate($this->getProjectRootDir()),
      ],
    ];
  }

  /**
   * @hook on-event marvin:lint
   */
  public function onEventMarvinLint(): array {
    return [
      'marvin:lint:composer-validate' => [
        'weight' => -201,
        'task' => $this->getTaskComposerValidate($this->getProjectRootDir()),
      ],
    ];
  }

  /**
   * Runs `composer validate`.
   *
   * @command marvin:lint:composer-validate
   * @bootstrap none
   */
  public function composerValidate(): CollectionBuilder {
    return $this->getTaskComposerValidate($this->getProjectRootDir());
  }

}
