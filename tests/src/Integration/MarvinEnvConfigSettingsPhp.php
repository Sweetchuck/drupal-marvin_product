<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Integration;

use Symfony\Component\Yaml\Yaml;

/**
 * @group marvin_product
 * @group drush-command
 */
class MarvinEnvConfigSettingsPhp extends UnishIntegrationTestCase {

  public function casesMarvinEnvConfigSettingsPhp(): array {
    $options = $this->getCommonCommandLineOptions();
    $envVars = $this->getCommonCommandLineEnvVars();

    $envConfigBase64 = base64_encode(Yaml::dump([
      [
        'sites' => [
          'all' => TRUE,
        ],
        'key' => ['a', 'b', 'c'],
        'value' => [
          'dev' => [
            'type' => 'value',
            'value' => 'all.a.b.c.dev',
          ],
          'stage' => [
            'type' => 'envVarNow',
            'value' => 'APP_A_B_C',
          ],
          'prod' => [
            'type' => 'envVarLater',
            'value' => 'APP_A_B_C',
          ],
        ],
      ]
    ]));

    return [
      'envVarNow' => [
        [
          'exitCode' => 0,
          'stdOutput' => implode("\n", [
            "\$a['b']['c'] = 'all.a.b.c.stage';",
          ]),
          'stdError' => '',
        ],
        [
          'target' => 'stage',
          'sites' => 'all,default',
        ] + $options,
        [
          "data://text/plain;base64,$envConfigBase64",
        ],
        [
          'APP_A_B_C' => 'all.a.b.c.stage',
        ] + $envVars,
      ],
      'envVarLater' => [
        [
          'exitCode' => 0,
          'stdOutput' => implode("\n", [
            "\$a['b']['c'] = getenv('APP_A_B_C');",
          ]),
          'stdError' => '',
        ],
        [
          'target' => 'prod',
          'sites' => 'all,default',
        ] + $options,
        [
          "data://text/plain;base64,$envConfigBase64",
        ],
        [
          'APP_A_B_C' => 'all.a.b.c.prod',
        ] + $envVars,
      ],
    ];
  }

  /**
   * @dataProvider casesMarvinEnvConfigSettingsPhp
   */
  public function testMarvinEnvConfigSettingsPhp(array $expected, array $options = [], array $args = [], array $envVars = []): void {
    $this->drush(
      'marvin:env-config:settings-php',
      $args,
      $options,
      NULL,
      NULL,
      $expected['exitCode'],
      NULL,
      $envVars
    );

    $actualStdError = $this->getErrorOutput();
    $actualStdOutput = $this->getOutput();

    static::assertSame($expected['stdError'], $actualStdError, 'StdError');
    static::assertSame($expected['stdOutput'], $actualStdOutput, 'StdOutput');
  }

}
