<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\PhpunitCommandsBase;
use Robo\Collection\CollectionBuilder;
use Sweetchuck\Robo\PHPUnit\PHPUnitTaskLoader;

class PhpunitCommands extends PhpunitCommandsBase {

  use PHPUnitTaskLoader;

  /**
   * @command marvin:test:unit
   * @bootstrap none
   */
  public function runUnit(): CollectionBuilder {
    return $this->getTaskPhpUnit(
      $this->getTestSuiteNamesByEnvironmentVariant(),
      $this->getGroupNames(),
      $this->getPhpVariant()
    );
  }

  protected function getGroupNames(): array {
    return [];
  }

  protected function getPhpVariant(): array {
    return [
      'enabled' => TRUE,
      'binDir' => PHP_BINDIR,
      'phpExecutable' => PHP_BINDIR . '/php',
      'phpdbgExecutable' => PHP_BINDIR . '/phpdbg',
      'phpIni' => '',
      'cli' => NULL,
      'version' => [],
    ];
  }

}
