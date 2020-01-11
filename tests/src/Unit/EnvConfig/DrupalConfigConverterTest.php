<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Unit;

use Drupal\marvin_product\EnvConfig\DrupalConfigConverter;
use PHPUnit\Framework\TestCase;

/**
 * @group marvin
 *
 * @covers \Drupal\marvin_product\EnvConfig\DrupalConfigConverter
 */
class DrupalConfigConverterTest extends TestCase {

  /**
   * @var \Drupal\marvin_product\EnvConfig\DrupalConfigConverter
   */
  protected $converter;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->converter = new DrupalConfigConverter();
  }

  public function casesGetKeyValuePairs(): array {
    return [
      'empty' => ['', [], ['default']],
      'basic' => [
        implode(PHP_EOL, [
          '$a = true;',
          '$b = 42;',
          "\$c = 'c.all.site-02';",
          "\$d = array (",
          "  'e' => false,",
          ');',
          "\$f['g']['h'] = 43;",
          "\$i = getenv('APP_FOO');",
          '',
        ]),
        [
          [
            'sites' => [
              'all' => TRUE,
            ],
            'key' => ['a'],
            'value' => TRUE,
          ],
          [
            'sites' => [
              'site-01' => TRUE,
            ],
            'key' => ['b'],
            'value' => 42,
          ],
          [
            'sites' => [
              'site-02' => TRUE,
            ],
            'key' => ['c'],
            'value' => 'c.site-02',
          ],
          [
            'sites' => [
              'all' => TRUE,
              'site-02' => TRUE,
            ],
            'key' => ['c'],
            'value' => 'c.all.site-02',
          ],
          [
            'sites' => [
              'all' => TRUE,
            ],
            'key' => ['d'],
            'value' => ['e' => FALSE],
          ],
          [
            'sites' => [
              'all' => TRUE,
            ],
            'key' => ['f', 'g', 'h'],
            'value' => 43,
          ],
          [
            'sites' => [
              'all' => TRUE,
            ],
            'key' => ['i'],
            'type' => 'envVarLater',
            'value' => 'APP_FOO',
          ],
        ],
        [
          'all' => TRUE,
          'site-01' => TRUE,
          'site-02' => FALSE,
        ],
      ],
    ];
  }

  /**
   * @dataProvider casesGetKeyValuePairs
   */
  public function testGetKeyValuePairs($expected, iterable $config, array $sites): void {
    static::assertEquals($expected, $this->converter->getKeyValuePairs($config, $sites));
  }

}
