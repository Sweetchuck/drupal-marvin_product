<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\Robo\InitLintReportersTaskLoader;
use Drush\Commands\marvin\LintCommandsBase;

class LintCommands extends LintCommandsBase {

  use InitLintReportersTaskLoader;

  /**
   * @command marvin:lint
   *
   * @bootstrap none
   *
   * @marvinInitLintReporters
   */
  public function cmdLintExecute() {
    return $this->delegate('');
  }

}
