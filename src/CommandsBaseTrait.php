<?php

declare(strict_types=1);

namespace Drupal\marvin_product;

use Drupal\marvin\CommandEvent as BaseCommandEvent;
use Robo\Collection\CallableTask;
use Robo\State\Data as RoboState;
use Sweetchuck\Utils\Filter\EnabledFilter;

trait CommandsBaseTrait {
  protected function getTaskDefsInitStateDataBase(BaseCommandEvent $event): array {
    $task = new CallableTask(
      function (RoboState $state) use ($event): int {
        $state['input'] = $event->input;
        $state['output'] = $event->output;
        $state['gitHookName'] = $event->gitHookName;

        return 0;
      },
      $event->collectionBuilder,
    );

    $classFqnParts = explode('\\', get_class($this));
    $provider = $classFqnParts[2] ?? '__unknown__';

    return [
      "Initialize-StateDataBase.$provider" => [
        'weight' => -999,
        'description' => 'Initializes basic state variables.',
        'task' => $task,
      ],
    ];
  }

  protected function getPhpVariant(): array {
    $phpVariants = array_filter(
      (array) $this->drushConfig->get('marvin.php.variants'),
      new EnabledFilter(),
    );

    $phpVariant = reset($phpVariants);
    if (!$phpVariant) {
      return [
        'id' => 'current',
        'enabled' => TRUE,
        'binDir' => '/usr/bin',
        'command' => [
          'executable' => 'php',
        ],
      ];
    }

    return $phpVariant;
  }

}
