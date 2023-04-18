<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Boot\DrupalBootLevels;
use Drush\Attributes as CLI;
use Drush\Commands\marvin\CommandsBase;
use Robo\Collection\CollectionBuilder;

class BuildCommands extends CommandsBase {

  /**
   * Generates a working code base from the Git repo.
   */
  #[CLI\Command(name: 'marvin:build')]
  #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
  public function cmdMarvinBuildExecute(): CollectionBuilder {
    return $this->delegate('build');
  }

}
