<?php

declare(strict_types = 1);

namespace Drupal\marvin_product\Robo;

use Drupal\marvin_product\Robo\Task\GitHooksDeployTask;
use Robo\Collection\CollectionBuilder;

trait GitHooksTaskLoader {

  /**
   * @return \Robo\Collection\CollectionBuilder|\Drupal\marvin_product\Robo\Task\GitHooksDeployTask
   */
  protected function taskMarvinGitHooksDeploy(array $options = []): CollectionBuilder {
    /** @var \Drupal\marvin_product\Robo\Task\GitHooksDeployTask $task */
    $task = $this->task(GitHooksDeployTask::class);
    $task->setOptions($options);

    return $task;
  }

}
