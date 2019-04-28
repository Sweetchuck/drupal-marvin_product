<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Unit\EnvConfig;

use Drupal\marvin_product\EnvConfig\EnvConfigHandler;
use PHPUnit\Framework\TestCase;

class EnvConfigHandlerTest extends TestCase {

  public function casesNormalize(): array {
    return [
      'empty' => [
        [],
        [],
        'dev',
      ],
      'basic' => [
        [
          [
            'key' => ['a'],
            'sites' => ['default' => TRUE],
            'type' => 'value',
            'value' => 'a-default',
          ],
          [
            'key' => ['b'],
            'sites' => ['all' => TRUE],
            'value' => 'b-dev',
            'type' => 'envVarNow',
          ],
        ],
        [
          [
            'key' => ['a'],
            'value' => [
              'default' => [
                'type' => 'value',
                'value' => 'a-default',
              ],
            ],
          ],
          [
            'key' => ['b'],
            'sites' => [
              'all' => TRUE,
            ],
            'value' => [
              'default' => [
                'type' => 'envVarNow',
                'value' => 'b-default',
              ],
              'dev' => [
                'value' => 'b-dev',
              ],
            ],
          ],
          [
            'key' => ['c'],
            'value' => [
              'prod' => [
                'type' => 'envVarNow',
                'value' => 'c-prod',
              ],
              'stage' => [
                'type' => 'envVarLater',
                'value' => 'c-stage',
              ],
            ],
          ],
          [
            'enabled' => FALSE,
            'key' => ['d'],
            'value' => [
              'dev' => [
                'type' => 'envVarNow',
                'value' => 'd-dev',
              ],
            ],
          ],
        ],
        'dev',
      ],
    ];
  }

  /**
   * @dataProvider casesNormalize
   */
  public function testNormalize(array $expected, array $envConfig, string $target): void {
    $handler = new EnvConfigHandler();

    static::assertSame($expected, $handler->normalize($envConfig, $target));
  }

}
