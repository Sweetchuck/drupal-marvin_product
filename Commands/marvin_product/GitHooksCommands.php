<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin_product\Utils as MarvinProductUtils;
use Drush\Commands\marvin\CommandsBase;
use Drupal\marvin_product\Robo\GitHooksTaskLoader;
use Robo\Collection\CollectionBuilder;

class GitHooksCommands extends CommandsBase {

  use GitHooksTaskLoader;

  /**
   * @hook on-event marvin:composer:post-install-cmd on-event marvin:composer:post-update-cmd
   */
  public function composerPostInstallAndUpdateCmd(): array {
    return [
      'marvin.gitHooks.deploy' => [
        'weight' => -200,
        'task' => $this->getTaskDeployGitHooks(),
      ],
    ];
  }

  /**
   * Deploys the Git hooks scripts into the PROJECT_ROOT/.git/hooks directory.
   *
   * @command marvin:git-hooks:deploy
   * @bootstrap none
   * @hidden
   */
  public function gitHooksDeploy(): CollectionBuilder {
    return $this->getTaskDeployGitHooks();
  }

  protected function getTaskDeployGitHooks(): CollectionBuilder {
    $config = $this->getConfig();
    $marvinProductDir = MarvinProductUtils::marvinProductDir();
    $drushConfigPaths = ['drush'];

    return $this
      ->taskMarvinGitHooksDeploy()
      ->setHookFilesSourceDir("$marvinProductDir/gitHooks")
      ->setCommonTemplateFileName("$marvinProductDir/gitHooks/_common.php")
      ->setProjectRootDir($this->getProjectRootDir())
      ->setComposerExecutable((string) $config->get('marvin.composerExecutable'))
      ->setDrushConfigPaths($drushConfigPaths);
  }

}
