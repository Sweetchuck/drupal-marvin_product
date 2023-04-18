<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Integration;

use Drush\TestTraits\DrushTestTrait;
use Symfony\Component\Filesystem\Path;
use weitzman\DrupalTestTraits\ExistingSiteBase;

class UnishIntegrationTestCase extends ExistingSiteBase {

  use DrushTestTrait;

  protected string $projectName = 'project_01';

  protected function getCommonCommandLineOptions(): array {
    return [
      'config' => [
        Path::join($this->getDrupalRoot(), '..', 'drush'),
      ],
    ];
  }

  protected function getCommonCommandLineEnvVars(): array {
    return [
      'HOME' => '/dev/null',
    ];
  }

  protected function getProjectRootDir(): string {
    return dirname($this->getDrupalRoot());
  }

  public function getMarvinProductRootDir(): string {
    return dirname(__DIR__, 3);
  }

  public function getDrupalRoot(): string {
    return Path::join(
      $this->getMarvinProductRootDir(),
      "tests/fixtures/{$this->projectName}/docroot",
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function convertKeyValueToFlag(string $key, mixed $value): string {
    if (!isset($value) || $value === TRUE) {
      return "--$key";
    }

    if (!is_array($value)) {
      return "--$key=" . self::escapeshellarg((string) $value);
    }

    $result = [];
    foreach ($value as $v) {
      $result[] = "--$key=" . self::escapeshellarg((string) $v);
    }

    return implode(' ', $result);
  }

}
