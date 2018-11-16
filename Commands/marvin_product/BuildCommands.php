<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\CommandsBase;

class BuildCommands extends CommandsBase {

  /**
   * @command marvin:build
   * @bootstrap none
   */
  public function build() {
    return $this->delegate('build');
  }

}
