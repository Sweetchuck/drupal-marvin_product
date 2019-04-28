<?php

declare(strict_types = 1);

namespace Drupal\marvin_product;

class Utils {

  public static function marvinProductDir() : string {
    return dirname(__DIR__);
  }

  public static function urlsHaveSameScheme(string $a, string $b): bool {
    // @todo Default scheme is "file://".
    return parse_url($a, PHP_URL_SCHEME) === parse_url($b, PHP_URL_SCHEME);
  }

  public static function booleanArray(array $items): array {
    return gettype(reset($items)) === 'boolean' ?
      $items
      : array_fill_keys($items, TRUE);
  }

}
