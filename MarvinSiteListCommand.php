<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin_product\Site\CollectEvent;
use Drupal\marvin_product\Site\Collection;
use Drupal\marvin_product\ContainerInitializer;
use Drupal\marvin_product\Site\Collector as SiteCollector;
use Drush\Attributes\Bootstrap as CliBootstrap;
use Drush\Attributes\DefaultFields as CliDefaultFields;
use Drush\Attributes\FieldLabels as CliFieldLabels;
use Drush\Attributes\Formatter as CliFormatter;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Formatters\DrushFormatterManager;
use Drush\Formatters\FormatterTrait;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
  name: self::NAME,
  description: 'Lists all sites.',
  aliases: [
    'marvin:site',
  ],
)]
#[CliBootstrap(level: DrupalBootLevels::NONE)]
#[CliFormatter(
  returnType: Collection::class,
  defaultFormatter: 'yaml',
)]
#[CliDefaultFields(
  fields: [
    'id',
    'label',
    'description',
  ],
)]
#[CliFieldLabels(
  labels: [
    'id' => 'ID',
    'label' => 'Label',
    'description' => 'Description',
    'weight' => 'Weight',
    'urls' => 'URLs',
  ],
)]
final class MarvinSiteListCommand extends Command {

  use AutowireTrait {
    create as protected autowireCreate;
  }
  use FormatterTrait;

  public const string NAME = 'marvin:site:list';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    ContainerInitializer::initialize($container);

    return self::autowireCreate($container);
  }

  public function __construct(
    #[Autowire('formatterManager')]
    protected DrushFormatterManager $formatterManager,
    #[Autowire('config')]
    protected DrushConfig $drushConfig,
    #[Autowire('eventDispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
    #[Autowire(SiteCollector::class)]
    protected SiteCollector $siteCollector,
  ) {
    parent::__construct();

    $this->eventDispatcher->addListener(
      CollectEvent::EVENT_COLLECT,
      $this->onEventMarvinSiteListCollect(...),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $input, OutputInterface $output): int {
    $this->formatterManager->write(
      $output,
      $input->getOption('format'),
      $this->siteCollector->collect(),
      $this
        ->getFormatterOptions()
        ->setInput($input)
        ->setOptions($input->getOptions()),
    );

    return Command::SUCCESS;
  }

  public function onEventMarvinSiteListCollect(CollectEvent $event): void {
    $sitesRaw = $this->drushConfig->get('marvin.sites') ?: [];
    foreach ($sitesRaw as $siteId => $siteConfig) {
      $event->collection->offsetSet($siteId, $siteConfig);
    }
  }

}
