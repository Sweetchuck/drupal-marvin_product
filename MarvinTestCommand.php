<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\Test\CommandEvent as TestCommandEvent;
use Drupal\marvin\MarvinTaskDefinitionCommandTrait;
use Drupal\marvin\Utils;
use Drupal\marvin_product\CommandsBaseTrait;
use Drush\Attributes\Bootstrap as CliBootstrap;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Robo\Contract\BuilderAwareInterface;
use Robo\TaskAccessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
  name: self::NAME,
  description: 'Runs all kind of tests.',
)]
#[CliBootstrap(level: DrupalBootLevels::NONE)]
final class MarvinTestCommand extends Command implements BuilderAwareInterface {

  use AutowireTrait;
  use TaskAccessor;
  use CommandsBaseTrait;
  use MarvinTaskDefinitionCommandTrait;

  public const string NAME = 'marvin:test';

  public function __construct(
    #[Autowire('eventDispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
    #[Autowire(Utils::class)]
    protected Utils $utils,
    #[Autowire(LoggerInterface::class)]
    protected LoggerInterface $logger,
  ) {
    parent::__construct();
    $this->eventDispatcher->addListener(
      TestCommandEvent::EVENT_RUN_TASKS_COLLECT,
      $this->onEventMarvinTestTasksCollect(...),
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function execute(InputInterface $input, OutputInterface $output): int {
    $event = $this->eventDispatcher->dispatch(
      new TestCommandEvent(
        $input,
        $output,
        NULL,
        $this->collectionBuilder(),
        [],
      ),
      TestCommandEvent::EVENT_RUN_TASKS_COLLECT,
    );
    $event = $this->eventDispatcher->dispatch(
      new TestCommandEvent(
        $event->input,
        $event->output,
        NULL,
        $event->collectionBuilder,
        $event->taskDefinitions,
      ),
      TestCommandEvent::EVENT_RUN_TASKS_ALTER,
    );

    return $this->mtdRun(
      self::NAME,
      $event->collectionBuilder,
      $event->taskDefinitions,
    );
  }

  public function onEventMarvinTestTasksCollect(TestCommandEvent $event): void {
    $event->taskDefinitions += $this->getTaskDefsInitStateDataBase($event);
  }

}
