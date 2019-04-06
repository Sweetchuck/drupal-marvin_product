<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Helper;

use Drupal\marvin_product\Robo\GitHooksTaskLoader;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Robo\Collection\CollectionBuilder;
use Robo\Common\TaskIO;
use Robo\Contract\BuilderAwareInterface;
use Robo\State\StateAwareInterface;
use Robo\State\StateAwareTrait;
use Robo\TaskAccessor;

class TaskBuilder implements BuilderAwareInterface, ContainerAwareInterface, StateAwareInterface {

  use TaskAccessor;
  use ContainerAwareTrait;
  use StateAwareTrait;
  use TaskIO;

  use GitHooksTaskLoader {
    taskMarvinGitHooksDeploy as public;
  }

  public function collectionBuilder(): CollectionBuilder {
    return CollectionBuilder::create($this->getContainer(), NULL);
  }

}
