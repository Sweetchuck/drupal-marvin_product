<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\LintCommandsBase as LintCommandsBase;

class LintCommands extends LintCommandsBase {

  /**
   * @command marvin:lint
   * @bootstrap none
   */
  public function lint() {
    return $this->delegate('');
  }

}
