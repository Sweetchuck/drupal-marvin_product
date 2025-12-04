<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\ContainerInitializer;
use Drupal\marvin\Robo\SymlinkTaskTrait;
use Drupal\marvin\RuntimeEnvironment\CommandEvent as RteCommandEvent;
use Drupal\marvin\RuntimeEnvironment\DetectCurrentEvent as RteDetectCurrentEvent;
use Drush\Attributes\Bootstrap as CliBootstrap;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Psr\Container\ContainerInterface;
use Robo\Contract\BuilderAwareInterface;
use Robo\TaskAccessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
  name: self::NAME,
  description: 'This is just an event subscriber.',
  hidden: TRUE,
)]
#[CliBootstrap(level: DrupalBootLevels::NONE)]
final class MarvinSubscriberRuntimeEnvironmentCommand extends Command implements BuilderAwareInterface {

  use TaskAccessor;
  use AutowireTrait {
    create as protected autowireCreate;
  }
  use SymlinkTaskTrait;

  public const string NAME = 'marvin-product:subscriber:runtime-environment';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    ContainerInitializer::initialize($container);

    return self::autowireCreate($container);
  }

  public function __construct(
    #[Autowire('config')]
    protected DrushConfig $drushConfig,
    #[Autowire('eventDispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct();

    $this->eventDispatcher->addListener(
      RteCommandEvent::EVENT_SWITCH_TASKS_COLLECT,
      $this->onEventSwitchTasksCollect(...),
    );

    $this->eventDispatcher->addListener(
      RteDetectCurrentEvent::EVENT_DETECT_CURRENT,
      $this->onEventDetectCurrent(...),
      -999,
    );
  }

  public function onEventSwitchTasksCollect(RteCommandEvent $event): void {
    $event->taskDefinitions['Upsert-DrushLocalYmlSymlink.marvin_product'] = [
      'description' => 'Creates or updates symlink to drush.local.yml for the current RTE.',
      'task' => $this
        ->taskMarvinUpsertSymlink()
        ->setSymlinkName('drush/drush.local.yml')
        ->setSymlinkSrc("drush/drush.{$event->rte['id']}.yml")
        ->setSymlinkDst("./drush.{$event->rte['id']}.yml"),
    ];
  }

  public function onEventDetectCurrent(RteDetectCurrentEvent $event): void {
    if ($event->currentId) {
      return;
    }

    if (getenv('IS_DDEV_PROJECT') === 'true'
      && $event->list->offsetExists('ddev')
    ) {
      $event->currentId = 'ddev';

      return;
    }

    if ($event->list->offsetExists('host')) {
      $event->currentId = 'host';
    }
  }

}
