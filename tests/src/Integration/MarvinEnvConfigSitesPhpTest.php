<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Integration;

use Symfony\Component\Yaml\Yaml;

/**
 * @group marvin_product
 * @group drush-command
 */
class MarvinEnvConfigSitesPhpTest extends UnishIntegrationTestCase {

  public function casesMarvinEnvConfigSitesPhp(): array {
    $options = $this->getCommonCommandLineOptions();

    $envConfigBase64 = base64_encode(Yaml::dump([
      'hosts' => [
        'host_a' => 'dir_a',
      ],
    ]));

    return [
      'basic' => [
        [
          'exitCode' => 0,
          'stdOutput' => implode("\n", [
            '<?php',
            '',
            '$sites = [',
            "  getenv('ABC_HOST_A_DEF') => 'dir_a',",
            '];',
          ]),
          'stdError' => '',
        ],
        [
          'parents' => [
            'hosts',
          ],
          'env-var-name-pattern' => 'ABC_{{ upper }}_DEF',
        ] + $options,
        [
          "data://text/plain;base64,$envConfigBase64",
        ],
      ],
    ];
  }

  /**
   * @dataProvider casesMarvinEnvConfigSitesPhp
   */
  public function testMarvinEnvConfigSitesPhp(array $expected, array $options = [], array $args = [], array $envVars = []): void {
    $this->drush(
      'marvin:env-config:sites-php',
      $args,
      $options,
      NULL,
      NULL,
      $expected['exitCode'],
      NULL,
      $envVars,
    );

    $actualStdError = $this->getErrorOutput();
    $actualStdOutput = $this->getOutput();

    static::assertSame($expected['stdError'], $actualStdError, 'StdError');
    static::assertSame($expected['stdOutput'], $actualStdOutput, 'StdOutput');
  }

}
