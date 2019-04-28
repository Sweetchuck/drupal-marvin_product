<?php

declare(strict_types = 1);

namespace Drupal\marvin_product\EnvConfig;

use Sweetchuck\Utils\Filter\ArrayFilterEnabled;

class EnvConfigHandler {

  /**
   * @var string
   */
  protected $defaultTarget = 'default';

  /**
   * @todo Validate - "key" is required.
   */
  public function normalize(array $envConfig, string $target): array {
    $return = [];

    foreach (array_filter($envConfig, new ArrayFilterEnabled()) as $item) {
      $item += [
        'sites' => ['default' => TRUE],
        'value' => [],
      ];

      $value = $item['value'];
      unset($item['value']);

      $default = $value[$this->defaultTarget] ?? [];
      if (array_key_exists($target, $value)) {
        $return[] = $item + $value[$target] + $default;
      }
      elseif ($default) {
        $return[] = $item + $default;
      }
    }

    return $return;
  }

}
