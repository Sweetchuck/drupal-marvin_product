<?php

declare(strict_types=1);

namespace Drupal\marvin_product\Site;

use Symfony\Contracts\EventDispatcher\Event;

class CollectEvent extends Event {

  public const string EVENT_COLLECT = 'marvin.site.list.collect';

  public const string EVENT_ALTER = 'marvin.site.list.alter';

  public function __construct(
    public readonly Collection $collection,
  ) {
  }

}
