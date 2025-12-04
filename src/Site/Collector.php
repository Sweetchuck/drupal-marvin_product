<?php

declare(strict_types=1);

namespace Drupal\marvin_product\Site;

use Drush\Commands\AutowireTrait;
use Sweetchuck\Utils\Comparer\ArrayValueComparer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Collector {

  use AutowireTrait;

  protected ?Collection $collection = NULL;

  public function __construct(
    #[Autowire('eventDispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

  public function reset(): static {
    $this->collection = NULL;

    return $this;
  }

  public function collect(): Collection {
    if ($this->collection === NULL) {
      $this->collection = new Collection();
      $comparer = $this->getComparer();

      /** @noinspection PhpExpressionResultUnusedInspection */
      $this->eventDispatcher->dispatch(
        new CollectEvent($this->collection),
        CollectEvent::EVENT_COLLECT,
      );
      $this->collection->populateDefaultValues();
      $this->collection->uasort($comparer);

      /** @noinspection PhpExpressionResultUnusedInspection */
      $this->eventDispatcher->dispatch(
        new CollectEvent($this->collection),
        CollectEvent::EVENT_ALTER,
      );
      $this->collection->populateDefaultValues();
      $this->collection->uasort($comparer);
    }

    return $this->collection;
  }

  public function getComparer(): callable {
    $comparer = new ArrayValueComparer();
    $comparer->setOptions($this->getComparerOptions());

    return $comparer;
  }

  /**
   * @phpstan-return array<string, mixed>
   */
  public function getComparerOptions(): array {
    return [
      'weight' => [
        'default' => 0,
      ],
      'label' => [
        'default' => '',
      ],
      'id' => [
        'default' => '',
      ],
    ];
  }

}
