<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\Lint\CommandsBase as LintCommandsBase;

class LintCommands extends LintCommandsBase {

  /**
   * @command marvin:lint
   * @bootstrap none
   *
   * @marvinArgPackages packages
   */
  public function lint(array $packages) {
    return $this->delegate('');
  }

}
