<?php

declare(strict_types=1);

namespace Drupal\marvin_product;

use Drupal\marvin\ContainerInitializer as ContainerInitializerMarvin;
use Drupal\marvin\ContainerInitializerBase;
use Drupal\marvin_product\Site\Collector as SiteCollector;

class ContainerInitializer extends ContainerInitializerBase {

  /**
   * {@inheritdoc}
   */
  public static function getDependencies(): array {
    return [
      ContainerInitializerMarvin::class,
    ];
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
