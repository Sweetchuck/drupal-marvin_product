<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\PhpcsCommandsBase;
use Robo\Collection\CollectionBuilder;

class PhpcsCommands extends PhpcsCommandsBase {

  /**
   * @hook pre-command marvin:git-hook:pre-commit
   */
  public function onEventPreCommandMarvinGitHookPreCommit() {
    $this->initLintReporters();
  }

  /**
   * @hook on-event marvin:git-hook:pre-commit
   */
  public function onEventMarvinGitHookPreCommit(): array {
    return [
      'marvin.lint.phpcs' => [
        'weight' => -200,
        'task' => $this->getTaskLintPhpcsExtension($this->getProjectRootDir()),
      ],
    ];
  }

  /**
   * @hook on-event marvin:lint
   */
  public function onEventMarvinLint(): array {
    return [
      'marvin.lint.phpcs' => [
        'weight' => -200,
        'task' => $this->getTaskLintPhpcsExtension($this->getProjectRootDir()),
      ],
    ];
  }

  /**
   * Runs PHP Code Sniffer.
   *
   * @command marvin:lint:phpcs
   * @bootstrap none
   *
   * @initLintReporters
   */
  public function lintPhpcs(): CollectionBuilder {
    return $this->getTaskLintPhpcsExtension('.');
  }

}
