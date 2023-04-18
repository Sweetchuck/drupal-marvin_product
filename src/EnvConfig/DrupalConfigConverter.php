<?php

declare(strict_types = 1);

namespace Drupal\marvin_product\EnvConfig;

use Drupal\marvin_product\Utils as MarvinProductUtils;
use Sweetchuck\Utils\Filter\EnabledFilter;

class DrupalConfigConverter {

  public function getKeyValuePairs(iterable $envConfig, array $sites): string {
    $sites = array_filter(
      MarvinProductUtils::booleanArray($sites),
      new EnabledFilter(),
    );

    $lines = [];
    foreach ($envConfig as $item) {
      $commonSites = array_intersect_key($item['sites'], $sites);
      if (!array_search(TRUE, $commonSites, TRUE)) {
        continue;
      }

      $lines[] = $this->getKeyValue($item);
    }

    return $lines ? implode("\n", $lines) . "\n" : '';
  }

  public function getKeyValue(array $item): string {
    return sprintf('%s = %s;', $this->getKey($item['key']), $this->getValue($item));
  }

  public function getKey(array $parents): string {
    // @todo Validate.
    $php = '$' . array_shift($parents);

    foreach ($parents as $parent) {
      $php .= sprintf('[%s]', var_export($parent, TRUE));
    }

    return $php;
  }

  public function getValue(array $item): string {
    switch ($item['type'] ?? 'value') {
      case 'envVarNow':
        return var_export(getenv($item['value']), TRUE);

      case 'envVarLater':
        return sprintf('getenv(%s)', var_export((string) $item['value'], TRUE));

      default:
        return var_export($item['value'], TRUE);

    }
  }

}
