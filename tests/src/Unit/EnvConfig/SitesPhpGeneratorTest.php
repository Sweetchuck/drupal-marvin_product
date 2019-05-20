<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Unit\EnvConfig;

use Drupal\marvin_product\EnvConfig\SitesPhpGenerator;
use PHPUnit\Framework\TestCase;

class SitesPhpGeneratorTest extends TestCase {

  public function casesGenerate(): array {
    return [
      'default' => [
        "<?php\n\n\$sites = [];\n",
        [],
      ],
      'no-mapping' => [
        "<?php\n\n\$sites = [];\n",
        [
          'envVarNamePattern' => '{{ original }}',
        ],
      ],
      'basic' => [
        implode("\n", [
          '<?php',
          '',
          '$sites = [',
          "  getenv('WS_HOST_SITE_A') => 'my-domain-a.hu',",
          "  getenv('WS_HOST_SITE_B') => 'my-domain-b.hu',",
          '];',
          '',
        ]),
        [
          'envVarNamePattern' => 'WS_HOST_{{ upper }}',
          'mapping' => [
            'site_a' => 'my-domain-a.hu',
            'site_b' => 'my-domain-b.hu',
          ],
        ],
      ],
    ];
  }

  /**
   * @dataProvider casesGenerate
   */
  public function testGenerate(string $expected, array $args): void {
    $generator = new SitesPhpGenerator();

    if (array_key_exists('mapping', $args)) {
      $generator->setMapping($args['mapping']);
    }

    if (array_key_exists('envVarNamePattern', $args)) {
      $generator->setEnvVarNamePattern($args['envVarNamePattern']);
    }

    static::assertSame($expected, $generator->generate());
  }

}
