<?php

declare(strict_types = 1);

namespace Drupal\marvin_product\EnvConfig;

class SitesPhpGenerator {

  /**
   * @var array
   */
  protected $mapping = [];

  public function getMapping(): array {
    return $this->mapping;
  }

  public function setMapping(array $mapping): static {
    $this->mapping = $mapping;

    return $this;
  }

  /**
   * @var string
   */
  protected $envVarNamePattern = '{{ upper }}';

  public function getEnvVarNamePattern(): string {
    return $this->envVarNamePattern;
  }

  public function setEnvVarNamePattern(string $envVarNamePattern): static {
    $this->envVarNamePattern = $envVarNamePattern ?: '{{ original }}';

    return $this;
  }

  public function generate() {
    $mapping = $this->getMapping();
    if (!count($mapping)) {
      return implode("\n", [
        '<?php',
        '',
        '$sites = [];',
        '',
      ]);
    }

    $lines = [
      '<?php',
      '',
      '$sites = [',
    ];

    $itemPattern = '  getenv(%s) => %s,';
    foreach ($mapping as $envVarNameBase => $sitesDir) {
      $lines[] = sprintf(
        $itemPattern,
        var_export($this->getEnvVarName((string) $envVarNameBase), TRUE),
        var_export((string) $sitesDir, TRUE)
      );
    }

    $lines[] = "];";
    $lines[] = '';

    return implode("\n", $lines);
  }

  protected function getEnvVarName(string $original): string {
    $replacementPairs = [
      '{{ original }}' => $original,
      '{{ upper }}' => mb_strtoupper($original),
    ];

    return strtr($this->getEnvVarNamePattern(), $replacementPairs);
  }

}
