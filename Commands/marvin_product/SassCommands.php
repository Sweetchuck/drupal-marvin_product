<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\CommandsBase;

class SassCommands extends CommandsBase {

  /**
   * @command marvin:lint:sass
   * @bootstrap none
   *
   * @initLintReporters
   */
  public function lint() {
    return $this->delegate('lint:sass');
  }

  /**
   * @command marvin:build:sass
   * @bootstrap none
   */
  public function build() {
    return $this->delegate('build:sass');
  }

}
