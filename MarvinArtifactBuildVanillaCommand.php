<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\ComposerInfo;
use Drupal\marvin_product\ContainerInitializer;
use Drupal\marvin\MarvinTaskDefinitionCommandTrait;
use Drupal\marvin\Robo\SymlinkTaskTrait;
use Drupal\marvin\Artifact\BuildActionEvent;
use Drupal\marvin\ArtifactType\ArtifactTypeListEvent;
use Drupal\marvin\Utils;
use Drush\Attributes\Bootstrap as CliBootstrap;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drupal\marvin\ArtifactBuildCommandTrait;
use Drush\Config\DrushConfig;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Robo\Collection\CallableTask;
use Robo\Collection\Tasks as LoopTaskLoader;
use Robo\Contract\BuilderAwareInterface;
use Robo\Contract\TaskInterface;
use Robo\State\Data as RoboStateData;
use Robo\Task\Composer\Tasks as ComposerTaskLoader;
use Robo\Task\Filesystem\Tasks as FileSystemStackTaskLoader;
use Robo\TaskAccessor;
use Sweetchuck\Utils\VersionNumber;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

#[AsCommand(
  name: self::NAME,
  description: 'Builds a "vanilla" release artifact.',
)]
#[CliBootstrap(level: DrupalBootLevels::NONE)]
final class MarvinArtifactBuildVanillaCommand extends Command implements
  BuilderAwareInterface,
  ContainerAwareInterface
{

  use AutowireTrait {
    create as protected autowireCreate;
  }
  use ContainerAwareTrait;
  use TaskAccessor;
  use LoopTaskLoader;
  use ComposerTaskLoader;
  use FileSystemStackTaskLoader;
  use SymlinkTaskTrait;
  use ArtifactBuildCommandTrait;
  use MarvinTaskDefinitionCommandTrait;

  public const string NAME = 'marvin:artifact:build:vanilla';

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
    #[Autowire(LoggerInterface::class)]
    protected LoggerInterface $logger,
    #[Autowire(Utils::class)]
    protected Utils $utils,
    #[Autowire(Filesystem::class)]
    protected Filesystem $fs,
  ) {
    parent::__construct();
    $this->eventDispatcher->addListener(
      ArtifactTypeListEvent::EVENT_COLLECT,
      $this->onEventMarvinArtifactTypeListCollect(...),
    );
    $this->eventDispatcher->addListener(
      BuildActionEvent::EVENT_TASKS_COLLECT,
      $this->onEventMarvinArtifactBuild(...),
    );
  }

  protected function getArtifactType(): array {
    return [
      'id' => 'vanilla',
      'provider' => 'marvin_product',
      'weight' => 999,
      'label' => 'Vanilla',
      'description' => 'Not customized',
    ];
  }

  public function onEventMarvinArtifactTypeListCollect(ArtifactTypeListEvent $event): void {
    $artifactType = $this->getArtifactType();
    $event->artifactTypeList->offsetSet($artifactType['id'], $artifactType);
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    parent::configure();
    $this
      ->addArgument(
        'versionNumber',
        InputArgument::REQUIRED,
        'The version number of the artifact. For example: 1.2.3-alpha1+20210101-abc1234',
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    // @todo Should be "vanilla" specific event?
    // @todo Artifact builder service.
    $event = $this->eventDispatcher->dispatch(
      new BuildActionEvent(
        $input,
        $output,
        $this->getArtifactType(),
        '.',
        $this->collectionBuilder(),
        [],
      ),
      BuildActionEvent::EVENT_TASKS_COLLECT,
    );
    $event = $this->eventDispatcher->dispatch(
      new BuildActionEvent(
        $input,
        $output,
        $event->artifactType,
        $event->srcDir,
        $event->collectionBuilder,
        $event->taskDefinitions,
      ),
      BuildActionEvent::EVENT_TASKS_ALTER
    );

    return $this->mtdRun(
      self::NAME,
      $event->collectionBuilder,
      $event->taskDefinitions,
    );
  }

  public function onEventMarvinArtifactBuild(BuildActionEvent $event): void {
    if ($event->artifactType['id'] !== $this->getArtifactType()['id']) {
      return;
    }

    $provider = 'marvin_product';

    $this->onEventMarvinArtifactBuildBase($event, 'marvin_product');
    $event->taskDefinitions += [
      "Resolve-RelativePackagePaths.$provider" => [
        'weight' => -710,
        'task' => $this->artifactBuildTaskResolveRelativePackagePaths($event),
      ],
      "Move-DrupalDocroot.$provider" => [
        'weight' => -705,
        'task' => $this->artifactBuildTaskMoveDrupalDocroot($event),
      ],
      "Invoke-ComposerUpdate.$provider" => [
        'weight' => 0,
        'task' => $this->artifactBuildTaskComposerUpdate($event),
      ],
      "Collect-GitIgnoreEntries.$provider" => [
        'weight' => 10,
        'task' => $this->artifactBuildTaskCollectGitIgnoreEntries($event),
      ],
      "Write-GitIgnore.$provider" => [
        'weight' => 20,
        'task' => $this->artifactBuildTaskDumpGitIgnore($event),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function artifactBuildInitialStateData(BuildActionEvent $event): array {
    $versionNumber = VersionNumber::createFromString($event->input->getArgument('versionNumber'));
    $composerJsonFileName = $this->utils->getComposerJsonFileName();
    $composerInfo = ComposerInfo::create('.', $composerJsonFileName);
    $oldDrupalRootDir = $composerInfo->getDrupalRootDir();

    return [
      'artifactType' => $event->artifactType,
      'composerInfo' => $composerInfo,
      'composerJsonFileName' => $composerJsonFileName,
      'versionNumber' => $versionNumber,

      // Absolute path to the project root directory. (Parent of composer.json).
      //'projectRootDir' => $composerInfo->getWorkingDirectory(),
      //'projectRootDir' => $this->drushConfig->get('env.cwd'),
      'projectRootDir' => $this->drushConfig->get('runtime.project'),

      // Relative from "projectRootDir".
      'srcDir' => $event->srcDir,

      // Relative from "projectRootDir".
      'buildDir' => Path::join(
        $this->drushConfig->get('marvin.artifactDir'),
        (string) $versionNumber,
        $event->artifactType['id'],
      ),

      // Relative from "projectRootDir".
      'oldDrupalRootDir' => $oldDrupalRootDir,

      // Relative from "projectRootDir".
      'newDrupalRootDir' => $oldDrupalRootDir,

      'filesToCopyDefinitions' => $this->drushConfig->get('marvin_product.artifact.vanilla.filesToCopyDefinitions'),
      'filesToCopy' => new Finder(),

      'filesToDeleteDefinitions' => $this->drushConfig->get('marvin_product.artifact.vanilla.filesToDeleteDefinitions'),
      'filesToDelete' => new Finder(),
    ];
  }

  protected function artifactBuildTaskCollectExtensions(BuildActionEvent $event): TaskInterface {
    return new CallableTask(
      function (RoboStateData $state): int {
        /** @var \Drupal\marvin\ComposerInfo $composerInfo */
        $composerInfo = $state['composerInfo'];
        $drupalRootDir = $composerInfo->getDrupalRootDir();

        $result = $this
         ->taskGitListFiles()
         ->setPaths([
           // @todo Currently only one level deep sub-modules are supported.
           "$drupalRootDir/modules/custom/*/*.info.yml",
           "$drupalRootDir/modules/custom/*/modules/*/*.info.yml",
           "$drupalRootDir/profiles/custom/*/*.info.yml",
           "$drupalRootDir/themes/custom/*/*.info.yml",
         ])
         ->run();

        if (!$result->wasSuccessful()) {
          $this->logger->error(
            'Failed to collect subExtensions. {error.message}',
            [
              'error.message' => $result->getMessage(),
            ],
          );

          return 1;
        }

        $buildDir = $state['buildDir'];
        $state['subExtensions'] = [];
        /** @var \Sweetchuck\Robo\Git\ListFilesItem $file */
        foreach ($result['files'] as $file) {
          $state['subExtensions'][] = [
            'dir' => Path::join($buildDir, Path::getDirectory($file->fileName)),
          ];
        }

        return 0;
      },
      $event->collectionBuilder,
    );
  }

  protected function artifactBuildTaskResolveRelativePackagePaths(BuildActionEvent $event): TaskInterface {
    // @todo Native task or a handler service.
    return new CallableTask(
      function(RoboStateData $state): int {
        $logContext = [
          'taskName' => 'Resolve-RelativePackagePaths',
        ];

        $composerInfo = ComposerInfo::create(
          $state['buildDir'],
          $state['composerJsonFileName'],
        );
        $json = $composerInfo->getJson();
        if (empty($json['repositories'])) {
          $this->logger->info('{taskName} - empty repositories', $logContext);

          return 0;
        }

        $this->logger->debug('{taskName} - Begin', $logContext);

        $changed = FALSE;
        $relative = Path::makeRelative($state['srcDir'], $state['buildDir']);
        foreach ($json['repositories'] as $repoId => $repo) {
          if (!Path::isRelative($repo['url'])) {
            continue;
          }

          $newUrl = $relative . '/' . $repo['url'];

          $logContext['oldUrl'] = $repo['url'];
          $logContext['newUrl'] = $newUrl;
          $this->logger->notice('{taskName} - {oldUrl} => {newUrl}', $logContext);

          $repo['url'] = $newUrl;
          $repo['options']['symlink'] = FALSE;

          $json['repositories'][$repoId] = $repo;
          $changed = TRUE;
        }

        if ($changed) {
          $this->fs->dumpFile(
            $composerInfo->getJsonFilePath(),
            json_encode($json, $this->utils->getJsonEncodeFlags()),
          );

          $composerInfo->invalidate();
        }

        $this->logger->debug('{taskName} - End', $logContext);

        return 0;
      },
      $event->collectionBuilder,
    );
  }

  /**
   * Currently the depth difference is not supported.
   *
   * Depth difference can cause problems with the relative paths,
   * for example $config_directories[sync] = ../config/sync.
   *
   * OK     docroot   => web
   * MAYBE  a/docroot => b/web
   * NOT OK docroot   => a/web
   * NOT OK a/web     => docroot
   */
  protected function artifactBuildTaskMoveDrupalDocroot(BuildActionEvent $event): TaskInterface {
    return new CallableTask(
      function (RoboStateData $state): int {
        $logContext = [
          'taskName' => 'Move-DrupalDocroot',
          'oldDrupalRootDir' => $state['oldDrupalRootDir'],
          'newDrupalRootDir' => $state['newDrupalRootDir'],
        ];

        if ($state['oldDrupalRootDir'] === $state['newDrupalRootDir']) {
          $this->logger->debug(
            '{taskName} - old and new DrupalRootDir is the same. <info>{oldDrupalRootDir}</info>',
            $logContext,
          );

          return 0;
        }

        $this->logger->info(
          '{taskName} - from <info>{oldDrupalRootDir}</info> to <info>{newDrupalRootDir}</info>',
          $logContext,
        );

        $this->fs->rename(
          Path::join($state['buildDir'], $state['oldDrupalRootDir']),
          Path::join($state['buildDir'], $state['newDrupalRootDir'])
        );

        $drushYmlFileName = Path::join($state['buildDir'], 'drush', 'drush.yml');
        if ($this->fs->exists($drushYmlFileName)) {
          $pattern = '${drush.vendor-dir}/../%s';
          // @todo Figure out a better way to preserve the comments.
          $drushYmlContent = strtr(
            $this->fs->readFile($drushYmlFileName),
            [
              sprintf($pattern, $state['oldDrupalRootDir']) => sprintf($pattern, $state['newDrupalRootDir']),
            ]
          );

          $this->fs->dumpFile($drushYmlFileName, $drushYmlContent);
        }

        $composerInfo = ComposerInfo::create($state['buildDir'], $this->utils->getComposerJsonFileName());
        $json = $composerInfo->getJson();
        $installerPaths = $json['extra']['installer-paths'] ?? [];
        $json['extra']['installer-paths'] = [];
        $pattern = '@^' . preg_quote($state['oldDrupalRootDir'] . '/', '@') . '@u';

        foreach ($installerPaths as $oldPath => $conditions) {
          $newPath = preg_replace(
            $pattern,
            $state['newDrupalRootDir'] . '/',
            $oldPath
          );

          $json['extra']['installer-paths'][$newPath] = $conditions;
        }

        $this->fs->dumpFile(
          $composerInfo->getJsonFilePath(),
          json_encode($json, $this->utils->getJsonEncodeFlags())
        );

        $composerInfo->invalidate();

        return 0;
      },
      $event->collectionBuilder,
    );
  }

  protected function artifactBuildTaskComposerUpdate(BuildActionEvent $event): TaskInterface {
    return $this
      ->taskComposerUpdate($this->drushConfig->get('marvin.composerExecutable'))
      ->noDev()
      ->noInteraction()
      ->option('no-progress')
      ->option('lock')
      ->deferTaskConfiguration('dir', 'buildDir');
  }

  protected function artifactBuildTaskCollectGitIgnoreEntries(BuildActionEvent $event): TaskInterface {
    return new CallableTask(
      function (RoboStateData $state): int {
        $w = 0;
        $drupalRootDir = $state['newDrupalRootDir'];
        $state['.gitignore'] = [
          "/$drupalRootDir/sites/*/files/" => ++$w,
          '/sites/*/backup/' => ++$w,
          '/sites/*/php_storage/' => ++$w,
          '/sites/*/private/' => ++$w,
          '/sites/*/hash_salt.txt' => ++$w,
        ];

        return 0;
      },
      $event->collectionBuilder,
    );
  }

  protected function artifactBuildTaskDumpGitIgnore(BuildActionEvent $event): TaskInterface {
    return new CallableTask(
      function (RoboStateData $state): int {
        asort($state['.gitignore']);
        $content = implode("\n", array_keys($state['.gitignore'])) . "\n";
        $fileName = Path::join($state['buildDir'], '.gitignore');
        try {
          $this->fs->dumpFile($fileName, $content);
        }
        catch (IOException $error) {
          $this->logger->error(
            '{taskName} - Failed to write <info>{fileName}</info>. {exception.message}',
            [
              'taskName' => 'Dump-GitIgnore',
              'fileName' => $fileName,
              'exception.message' => $error->getMessage(),
            ],
          );

          return 1;
        }

        return 0;
      },
      $event->collectionBuilder,
    );
  }

}
