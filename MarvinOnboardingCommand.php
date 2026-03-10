<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\ComposerInfo;
use Drupal\marvin\RuntimeEnvironment\CommandEvent as RteCommandEvent;
use Drupal\marvin\RuntimeEnvironment\Handler as RteHandler;
use Drupal\marvin\MarvinTaskDefinitionCommandTrait;
use Drupal\marvin\Utils;
use Drupal\marvin_product\ContainerInitializer;
use Drupal\marvin_product\Onboarding\CommandEvent as OnboardingCommandEvent;
use Drupal\marvin_product\Site\Collector as SiteCollector;
use Drush\Attributes\Bootstrap as CliBootstrap;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Robo\Collection\CallableTask;
use Robo\Collection\CollectionBuilder;
use Robo\Collection\Tasks as LoopTaskLoader;
use Robo\State\StateAwareInterface;
use Robo\Task\Filesystem\Tasks as FilesystemTaskLoader;
use Robo\Task\File\Tasks as FileTaskLoader;
use Robo\Contract\BuilderAwareInterface;
use Robo\Contract\TaskInterface;
use Robo\State\Data as RoboState;
use Robo\TaskAccessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
  name: self::NAME,
  description: 'Initializes non-VCS tracked files and configuration values.',
  help: 'Usually this happens before "marvin:build".'
)]
#[CliBootstrap(level: DrupalBootLevels::NONE)]
final class MarvinOnboardingCommand extends Command implements
  BuilderAwareInterface,
  ContainerAwareInterface
{

  use AutowireTrait {
    create as protected autowireCreate;
  }
  use ContainerAwareTrait;
  use TaskAccessor;
  use LoopTaskLoader;
  use FilesystemTaskLoader;
  use FileTaskLoader;
  use MarvinTaskDefinitionCommandTrait;

  public const string NAME = 'marvin:onboarding';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    ContainerInitializer::initialize($container);

    return self::autowireCreate($container);
  }

  public function __construct(
    #[Autowire(Filesystem::class)]
    protected Filesystem $fs,
    #[Autowire(LoggerInterface::class)]
    protected LoggerInterface $logger,
    #[Autowire('eventDispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
    #[Autowire('config')]
    protected DrushConfig $drushConfig,
    #[Autowire(Utils::class)]
    protected Utils $utils,
    #[Autowire(RteHandler::class)]
    protected RteHAndler $rteHandler,
    #[Autowire(SiteCollector::class)]
    protected SiteCollector $siteCollector,
  ) {
    parent::__construct();

    $this->eventDispatcher->addListener(
      OnboardingCommandEvent::EVENT_BUILD_TASKS_COLLECT,
      $this->onEventMarvinOnboardingTasksCollect(...),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $input, OutputInterface $output): int {
    $event = $this->eventDispatcher->dispatch(
      new OnboardingCommandEvent(
        $input,
        $output,
        NULL,
        $this->collectionBuilder(),
        [],
      ),
      OnboardingCommandEvent::EVENT_BUILD_TASKS_COLLECT,
    );
    $event = $this->eventDispatcher->dispatch(
      new OnboardingCommandEvent(
        $input,
        $output,
        NULL,
        $event->collectionBuilder,
        $event->taskDefinitions,
      ),
      OnboardingCommandEvent::EVENT_BUILD_TASKS_ALTER,
    );

    return $this->mtdRun(
      self::NAME,
      $event->collectionBuilder,
      $event->taskDefinitions,
    );
  }

  /**
   * @see \Drupal\marvin_product\Onboarding\ActionEvent::EVENT_BUILD_TASKS_COLLECT
   */
  public function onEventMarvinOnboardingTasksCollect(OnboardingCommandEvent $event): void {
    $event->taskDefinitions += [
      'Initialize-StateDataBase.marvin_product' => [
        'weight' => -999,
        'description' => '',
        'task' => $this->onboardingTaskInit($event),
      ],
      'Create-RequiredDirs.marvin_product' => [
        'weight' => 0,
        'description' => 'Create required directories.',
        'task' => $this->onboardingTaskCreateRequiredDirs($event),
      ],
      'Create-HashSaltFiles.marvin_product' => [
        'provider' => 'marvin_product',
        'weight' => 50,
        'description' => 'Creates hash_salt.txt files in each site directory.',
        'task' => $this->onboardingTaskCreateHashSaltFiles($event),
      ],
      'Create-SettingsHostPhp.marvin_product' => [
        'weight' => 100,
        'description' => 'Creates settings.host.php file in each site directory.',
        'task' => $this->onboardingTaskCreateSettingsHostPhp($event),
      ],
      'Create-DrushHostYml.marvin_product' => [
        'weight' => 150,
        'description' => 'Creates drush.host.yml.',
        'task' => $this->onboardingTaskCreateDrushHostYml($event),
      ],
      'Switch-RuntimeEnvironment.marvin_product' => [
        'weight' => 300,
        'description' => 'Updates config files to the current runtime environment.',
        'task' => $this->onboardingTaskSwitchRuntimeEnvironment($event),
      ],
    ];
  }

  protected function onboardingTaskInit(OnboardingCommandEvent $event): TaskInterface {
    return new CallableTask(
      function(RoboState $state) use ($event): int {
        $composerJsonFileName = $this->utils->getComposerJsonFileName();
        $composerInfo = ComposerInfo::create('.', $composerJsonFileName);

        $state['composerInfo'] = $composerInfo;
        $state['cwd'] = getcwd();
        $state['projectRootDir'] = $this->drushConfig->get('runtime.project');

        if ($state['cwd'] === $state['projectRootDir']) {
          $state['projectRootDir'] = '.';
        }
        $state['drupalRoot'] = $composerInfo->getDrupalRootDir();
        $state['sites'] = $this->siteCollector->collect()->getArrayCopy();
        $state['runtimeEnvironment'] = $this->rteHandler->getCurrent();

        return 0;
      },
      $event->collectionBuilder,
    );
  }

  protected function onboardingTaskCreateRequiredDirs(OnboardingCommandEvent $event): TaskInterface {
    $taskForEach = $this->taskForEach();
    $taskForEach
      ->iterationMessage('Create required directories for site: {key}')
      ->deferTaskConfiguration('setIterable', 'sites')
      ->withBuilder(function (CollectionBuilder $builder, string $key, $site) use ($taskForEach): void {
        if (!is_array($site)) {
          // Robo tries to figure it out how many subtasks will be here.
          $builder->addCode(function (): int {
            return 0;
          });

          return;
        }

        /** @var array $site */
        $state = $taskForEach->getState();
        $projectRootDir = $state['projectRootDir'];
        $drupalRoot = $state['drupalRoot'];
        $siteId = $site['id'];

        // @todo Get these directory paths from the actual configuration.
        $builder->addTask(
          $this
            ->taskFilesystemStack()
            ->mkdir("$projectRootDir/$drupalRoot/sites/$siteId/files")
            ->mkdir("$projectRootDir/$drupalRoot/../sites/all/translations")
            ->mkdir("$projectRootDir/$drupalRoot/../sites/$siteId/config/prod")
            ->mkdir("$projectRootDir/$drupalRoot/../sites/$siteId/php_storage")
            ->mkdir("$projectRootDir/$drupalRoot/../sites/$siteId/private")
            ->mkdir("$projectRootDir/$drupalRoot/../sites/$siteId/temporary")
            ->mkdir("$projectRootDir/$drupalRoot/../sites/$siteId/backup")
        );
      });

    return $taskForEach;
  }

  protected function onboardingTaskCreateHashSaltFiles(OnboardingCommandEvent $event): TaskInterface {
    $taskForEach = $this->taskForEach();
    $taskForEach
      ->iterationMessage('Create required directories for site: {key}')
      ->deferTaskConfiguration('setIterable', 'sites')
      ->withBuilder(function (CollectionBuilder $builder, string $key, $site) use ($taskForEach): void {
        if (!is_array($site)) {
          // Robo tries to figure out how many subtasks will be here.
          $builder->addCode(function (): int {
            return 0;
          });

          return;
        }

        $builder->addTask(
          $this->onboardingTaskCreateHashSaltFile(
            $taskForEach,
            $builder,
            $site,
          ),
        );
      });

    return $taskForEach;
  }

  protected function onboardingTaskCreateHashSaltFile(
    StateAwareInterface $taskForEach,
    CollectionBuilder $builder,
    array $site,
  ): TaskInterface {
    return new CallableTask(
      function() use ($taskForEach, $site): int {
        $state = $taskForEach->getState();
        $projectRootDir = $state['projectRootDir'];
        $drupalRoot = $state['drupalRoot'];

        $siteId = $site['id'];
        $filePath = Path::join(
          $projectRootDir,
          $drupalRoot,
          '..',
          'sites',
          $siteId,
          'hash_salt.txt',
        );
        $loggerArgs = [
          'filePath' => $filePath,
        ];
        if ($this->fs->exists($filePath)) {
          $this->logger->info(
            'File "<info>{filePath}</info>" already exists',
            $loggerArgs,
          );

          return 0;
        }

        $this->logger->info(
          'Crate file "<info>{filePath}</info>"',
          $loggerArgs,
        );
        $result = $this
          ->taskWriteToFile($filePath)
          ->text($this->utils->generateHashSalt())
          ->run();

        if (!$result->wasSuccessful()) {
          $loggerArgs['errorMessage'] = $result->getMessage();
          $this->logger->error(
            'Crate file "<info>{filePath}</info>" failed. {errorMessage}',
            $loggerArgs,
          );

          return 1;
        }

        return 0;
      },
      $builder,
    );
  }

  protected function onboardingTaskCreateSettingsHostPhp(OnboardingCommandEvent $event): TaskInterface {
    $taskForEach = $this->taskForEach();
    $taskForEach
      ->iterationMessage('Create settings.host.php for site: {key}')
      ->deferTaskConfiguration('setIterable', 'sites')
      ->withBuilder(function (CollectionBuilder $builder, string $key, $site) use ($taskForEach): void {
        if (!is_array($site)) {
          $builder->addCode(function (): int {
            return 0;
          });

          return;
        }

        $state = $taskForEach->getState();
        $projectRootDir = $state['projectRootDir'];
        $drupalRoot = $state['drupalRoot'];

        $builder->addCode(function () use ($projectRootDir, $drupalRoot, $site) {
          $siteId = $site['id'];
          $dst = "$drupalRoot/sites/$siteId/settings.host.php";
          if ($this->fs->exists($dst)) {
            $this->logger->debug(
              'File "<info>{fileName}</info>" already exists',
              [
                'fileName' => $dst,
              ],
            );

            return 0;
          }

          $src = $this->getExampleSettingsLocalPhp($projectRootDir, $drupalRoot, $site);
          if (!$src) {
            $this->logger->debug('There is no source for "settings.host.php"');

            return 0;
          }

          $result = $this
            ->taskFilesystemStack()
            ->copy($src, $dst)
            ->run();

          return $result->wasSuccessful() ? 0 : 1;
        });
      });

    return $taskForEach;
  }

  protected function onboardingTaskCreateDrushHostYml(OnboardingCommandEvent $event): TaskInterface {
    return new CallableTask(
      function(RoboState $state): int {
        $hostFilePath = 'drush/drush.host.yml';
        $exampleFilePath = 'drush/drush.local.example.yml';
        $loggerArgs = [
          'hostFilePath' => $hostFilePath,
          'exampleFilePath' => $exampleFilePath,
        ];

        $runtimeEnvironment = $state['runtimeEnvironment'];
        if ($runtimeEnvironment['id'] !== 'host') {
          $this->logger->info(
            '{hostFilePath} skipped because there is no "host" runtime environment',
            $loggerArgs,
          );

          return 0;
        }

        if ($this->fs->exists($hostFilePath)) {
          $this->logger->info(
            'update option.uri in {hostFilePath}',
            $loggerArgs,
          );
          $content = $this->fs->readFile($hostFilePath);
        }
        elseif ($this->fs->exists($exampleFilePath)) {
          $this->logger->info(
            'create {hostFilePath} based on {exampleFilePath}',
            $loggerArgs,
          );
          $content = str_replace(
            implode("\n", [
              '##',
              '# Copy this file as "drush.host.yml".',
              '##',
              '',
            ]),
            '',
            $this->fs->readFile($exampleFilePath),
          );
        }
        else {
          $this->logger->info(
            'create {hostFilePath} with default content',
            $loggerArgs,
          );
          $content = $this->getDrushLocalYmlContent();
        }

        $siteId = array_key_first($state['sites']);
        $site = $state['sites'][$siteId];
        $uriInfo = reset($site['uriList']);
        $uri = $uriInfo['uri'];

        $content = preg_replace(
          '/^ {2}uri: .+$/um',
          '  uri: ' . $this->utils->escapeYamlValueString($uri),
          $content,
        );

        $this->fs->dumpFile($hostFilePath, $content);

        return 0;
      },
      $event->collectionBuilder,
    );
  }

  protected function onboardingTaskSwitchRuntimeEnvironment(OnboardingCommandEvent $event): TaskInterface {
    return new CallableTask(
      function(RoboState $state) use ($event): int {
        $rte = $state['runtimeEnvironment'];
        if (!$rte) {
          // @todo Log this.
          return 0;
        }

        $rteEvent = $this->eventDispatcher->dispatch(
          new RteCommandEvent(
            $event->input,
            $event->output,
            $event->gitHookName,
            $this->collectionBuilder(),
            [],
            $rte,
          ),
          RteCommandEvent::EVENT_SWITCH_TASKS_COLLECT,
        );

        $rteEvent = $this->eventDispatcher->dispatch(
          new RteCommandEvent(
            $rteEvent->input,
            $rteEvent->output,
            $rteEvent->gitHookName,
            $rteEvent->collectionBuilder,
            $rteEvent->taskDefinitions,
            $rte,
          ),
          RteCommandEvent::EVENT_SWITCH_TASKS_ALTER,
        );

        return $this->mtdRun(
          RteCommandEvent::EVENT_SWITCH_TASKS_COLLECT,
          $rteEvent->collectionBuilder,
          $rteEvent->taskDefinitions,
        );
      },
      $event->collectionBuilder,
    );
  }

  protected function getExampleSettingsLocalPhp(string $projectRoot, string $drupalRoot, array $site): ?string {
    $siteId = $site['id'];
    $fileNames = [
      "$projectRoot/$drupalRoot/sites/$siteId/settings.local.example.php",
      "$projectRoot/$drupalRoot/sites/$siteId/example.settings.local.php",
      "$projectRoot/$drupalRoot/sites/example.settings.local.php",
    ];

    foreach ($fileNames as $fileName) {
      if ($this->fs->exists($fileName)) {
        return $fileName;
      }
    }

    return NULL;
  }

  protected function getDrushLocalYmlContent(): string {
    return <<<'YAML'
      options:
        uri: 'APP_PRIMARY_URI'
      YAML;
  }

}
