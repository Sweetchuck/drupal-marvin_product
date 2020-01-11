<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Integration;

/**
 * @group marvin_product
 * @group drush-command
 */
class MarvinLintComposerValidateTest extends UnishIntegrationTestCase {

  public function testMarvinStatusReport(): void {
    $expected = [
      'stdError' => implode(PHP_EOL, [
        ' [Composer\Validate] Running composer validate in ' . $this->getMarvinProductRootDir(),
      ]),
      'stdOutput' => './composer.json is valid',
      'exitCode' => 0,
    ];

    $this->drush(
      'marvin:lint:composer-validate',
      [],
      $this->getCommonCommandLineOptions(),
      NULL,
      NULL,
      $expected['exitCode'],
      NULL,
      $this->getCommonCommandLineEnvVars()
    );

    $actualStdError = $this->getErrorOutput();
    $actualStdOutput = $this->getOutput();

    static::assertContains($expected['stdError'], $actualStdError, 'StdError');
    static::assertContains($expected['stdOutput'], $actualStdOutput, 'StdOutput');
  }

}
