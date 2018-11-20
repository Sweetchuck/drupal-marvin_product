<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\Lint\ComposerCommandsBase;
use Robo\Collection\CollectionBuilder;

class ComposerCommands extends ComposerCommandsBase {

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

  /**
   * @todo Check that if $path is empty.
   */
  protected function getTaskComposerStatus(string $refPrevious, string $refHead, array $paths): \Closure {
    return function () use ($refPrevious, $refHead, $paths): int {
      $cmdPattern = '%s diff --exit-code --name-only %s..%s --';
      $cmdArgs = [
        escapeshellcmd($this->getGitExecutable()),
        escapeshellarg($refPrevious),
        escapeshellarg($refHead),
      ];

      $cmdPattern .= str_repeat(' %s', count($paths));
      foreach ($paths as $path) {
        $cmdArgs[] = escapeshellarg($path);
      }

      $cmd = vsprintf($cmdPattern, $cmdArgs);

      $result = $this
        ->taskExec($cmd)
        ->run();

      if (!$result->wasSuccessful()) {
        $this->say('composer.{json,lock} has been changed changed. Run `composer install`');
      }

      return 0;
    };
  }

}
