<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\Build\CommandEvent as BuildCommandEvent;
use Drupal\marvin\MarvinTaskDefinitionCommandTrait;
use Drupal\marvin\Utils;
use Drupal\marvin_product\ContainerInitializer;
use Drush\Boot\DrupalBootLevels;
use Drush\Attributes\Bootstrap as CliBootstrap;
use Drush\Commands\AutowireTrait;
use Psr\Container\ContainerInterface;
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
  description: 'Generates a working code base from the Git repo.',
)]
#[CliBootstrap(level: DrupalBootLevels::NONE)]
final class MarvinBuildCommand extends Command implements BuilderAwareInterface {

  use AutowireTrait {
    create as protected autowireCreate;
  }
  use TaskAccessor;
  use MarvinTaskDefinitionCommandTrait;

  public const string NAME = 'marvin:build';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    ContainerInitializer::initialize($container);

    return self::autowireCreate($container);
  }

  public function __construct(
    #[Autowire('eventDispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
    #[Autowire(Utils::class)]
    protected Utils $utils,
    #[Autowire(LoggerInterface::class)]
    protected LoggerInterface $logger,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $input, OutputInterface $output): int {
    $event = $this->eventDispatcher->dispatch(
      new BuildCommandEvent(
        $input,
        $output,
        NULL,
        $this->collectionBuilder(),
        [],
      ),
      BuildCommandEvent::EVENT_BUILD_TASKS_COLLECT,
    );
    $event = $this->eventDispatcher->dispatch(
      new BuildCommandEvent(
        $input,
        $output,
        NULL,
        $event->collectionBuilder,
        $event->taskDefinitions,
      ),
      BuildCommandEvent::EVENT_BUILD_TASKS_ALTER,
    );

    return $this->mtdRun(
      self::NAME,
      $event->collectionBuilder,
      $event->taskDefinitions,
    );
  }

}
