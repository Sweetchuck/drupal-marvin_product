<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\PhpcsCommandsBase;
use Robo\Collection\CollectionBuilder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PhpcsCommands extends PhpcsCommandsBase {

  /**
   * @hook on-event marvin:composer-scripts:post-install-cmd
   */
  public function onEventComposerScriptsPostInstallCmd(InputInterface $input, OutputInterface $output, string $projectRoot): array {
    $tasks = [];

    if ($input->getOption('dev-mode')) {
      $tasks['marvin.phpcs.config.installed_paths'] = [
        'weight' => 100,
        'task' => $this->getTaskPhpcsConfigSetInstalledPaths($projectRoot),
      ];
    }

    return $tasks;
  }

  /**
   * @hook on-event marvin:composer-scripts:post-update-cmd
   */
  public function onEventComposerScriptsPostUpdateCmd(InputInterface $input, OutputInterface $output, string $projectRoot): array {
    return $this->onEventComposerScriptsPostInstallCmd($input, $output, $projectRoot);
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
   */
  public function lintPhpcs(): CollectionBuilder {
    return $this->getTaskLintPhpcsExtension('.');
  }

}
