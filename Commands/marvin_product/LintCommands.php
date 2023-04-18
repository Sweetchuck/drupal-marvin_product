<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\Attributes as MarvinCLI;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\marvin\LintCommandsBase;
use Robo\Collection\CollectionBuilder;

class LintCommands extends LintCommandsBase {

  /**
   * Runs all kind of static code analyzers.
   */
  #[CLI\Command(name: 'marvin:lint')]
  #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
  #[MarvinCLI\PreCommandInitLintReporters]
  public function cmdMarvinLintExecute(): CollectionBuilder {
    return $this->delegate('');
  }

}
