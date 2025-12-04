<?php

declare(strict_types=1);

namespace Drupal\marvin_product\Onboarding;

use Drupal\marvin\CommandEvent as BaseCommandEvent;

class CommandEvent extends BaseCommandEvent {

  public const string EVENT_BUILD_TASKS_COLLECT = 'marvin.onboarding.tasks.collect';

  public const string EVENT_BUILD_TASKS_ALTER = 'marvin.onboarding.tasks.alter';

}
