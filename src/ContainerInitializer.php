<?php

declare(strict_types=1);

namespace Drupal\marvin_product;

use Drupal\marvin\ContainerInitializerBase;
use Drupal\marvin_product\Site\Collector as SiteCollector;
use Psr\Container\ContainerInterface;

class ContainerInitializer extends ContainerInitializerBase {

  #[\Override]
  public static function isInitialized(ContainerInterface $container): bool {
    return $container->has(SiteCollector::class);
  }

  #[\Override]
  public static function getServiceInfoList(): array {
    return [
      SiteCollector::class => [
        'class' => SiteCollector::class,
        'shared' => TRUE,
        'arguments' => [
          '@eventDispatcher',
        ],
      ],
    ];
  }

}
