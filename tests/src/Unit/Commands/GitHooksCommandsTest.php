<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Unit\Commands;

use Drush\Commands\marvin_product\GitHooksCommands;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @group marvin_product
 *
 * @covers \Drush\Commands\marvin_product\GitHooksCommands<extended>
 */
class GitHooksCommandsTest extends TestCase {

  public function testGetCustomEventNamePrefix(): void {
    $commands = new GitHooksCommands();
    $methodName = 'getCustomEventNamePrefix';
    $class = new ReflectionClass($commands);
    $method = $class->getMethod($methodName);
    $method->setAccessible(TRUE);

    static::assertSame('marvin', $method->invokeArgs($commands, []));
  }

}
