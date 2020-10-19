<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin_product\Utils;
use Drupal\marvin_product\Utils as MarvinProductUtils;
use Drush\Commands\marvin\CommandsBase;
use Drupal\marvin_product\Robo\GitHooksTaskLoader;
use Robo\Collection\CollectionBuilder;
use Robo\State\Data as RoboStateData;

/**
 * Git hook scripts maintainer.
 *
 * Currently only the "deploy" action is supported.
 */
class GitHooksCommands extends CommandsBase {

  use GitHooksTaskLoader;

  /**
   * @hook on-event marvin:composer-scripts:post-install-cmd
   */
  public function onEventComposerScriptPostInstallCmd(): array {
    return [
      'marvin.gitHooks.deploy.configReader' => [
        'weight' => -201,
        'task' => $this->getTaskDeployGitHooksConfigReader(),
      ],
      'marvin.gitHooks.deploy' => [
        'weight' => -200,
        'task' => $this->getTaskDeployGitHooks(),
      ],
    ];
  }

  /**
   * @hook on-event marvin:composer-scripts:post-update-cmd
   */
  public function onEventComposerScriptPostUpdateCmd(): array {
    return $this->onEventComposerScriptPostInstallCmd();
  }

  /**
   * Deploys the Git hooks scripts into the PROJECT_ROOT/.git/hooks directory.
   *
   * @command marvin:git-hooks:deploy
   * @bootstrap none
   * @hidden
   */
  public function gitHooksDeploy(): CollectionBuilder {
    return $this
      ->collectionBuilder()
      ->addCode($this->getTaskDeployGitHooksConfigReader())
      ->addTask($this->getTaskDeployGitHooks());
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
      ->setDrushConfigPaths($drushConfigPaths)
      ->deferTaskConfiguration('setGitHooksToDeploy', 'gitHooksToDeploy');
  }

  /**
   * @return callable|\Robo\Contract\TaskInterface
   */
  protected function getTaskDeployGitHooksConfigReader() {
    $subscriberCollectorCallback = $this->getSubscriberCollectorCallback();

    return function (RoboStateData $data) use ($subscriberCollectorCallback): int {
      $config = $this->getConfig()->export();
      $data['gitHooksToDeploy'] = [];
      foreach (Utils::$gitHookNames as $gitHook) {
        $data['gitHooksToDeploy'][$gitHook] = $config['marvin']['git-hook'][$gitHook]['deploy'] ?? NULL;
        if ($data['gitHooksToDeploy'][$gitHook] !== NULL) {
          continue;
        }

        $data['gitHooksToDeploy'][$gitHook] = FALSE;
        /** @var callable $subscriber */
        foreach ($subscriberCollectorCallback($gitHook) as $subscriber) {
          if ($subscriber($gitHook)) {
            $data['gitHooksToDeploy'][$gitHook] = TRUE;

            break;
          }
        }
      }

      return 0;
    };
  }

  protected function getSubscriberCollectorCallback(): callable {
    return function (string $gitHook): array {
      $eventName = $this->getCustomEventName("git-hook:$gitHook:info");

      return $this->getCustomEventHandlers($eventName);
    };
  }

}
