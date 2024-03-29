<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\marvin\ComposerInfo;
use Drupal\marvin\Utils as MarvinUtils;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\marvin\CommandsBase;
use Robo\Collection\CollectionBuilder;
use Robo\State\Data as RoboState;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\String\UnicodeString;

class OnboardingCommands extends CommandsBase {

  protected Filesystem $fs;

  public function __construct(?ComposerInfo $composerInfo = NULL, ?Filesystem $fs = NULL) {
    parent::__construct($composerInfo);
    $this->fs = $fs ?: new Filesystem();
  }

  #[CLI\Hook(
    type: HookManager::ON_EVENT,
    target: 'marvin:composer:post-install-cmd',
  )]
  public function onEventMarvinComposerPostInstallCmd(
    InputInterface $input,
    OutputInterface $output,
    string $projectRoot,
  ): array {
    return $this->onEventMarvinOnboarding();
  }

  #[CLI\Hook(
    type: HookManager::ON_EVENT,
    target: 'marvin:onboarding',
  )]
  public function onEventMarvinOnboarding(): array {
    return $this->getTaskDefsOnboardingInit()
      + $this->getTaskDefsOnboardingCreateRequiredDirs()
      + $this->getTaskDefsOnboardingHashSaltTxt()
      + $this->getTaskDefsOnboardingSettingsLocalPhp()
      + $this->getTaskDefsOnboardingDrushHostYml()
      + $this->getTaskDefsOnboardingRteSwitch();
  }

  /**
   * Setup the required files and configuration.
   *
   * The main goal is to make a working instance from the project after the
   * `composer install` command.
   */
  #[CLI\Command(name: 'marvin:onboarding')]
  #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
  public function cmdMarvinOnboardingExecute(array $options = []): CollectionBuilder {
    $cb = $this->delegate('onboarding');
    $cb->setProgressIndicator(NULL);

    return $cb;
  }

  protected function getTaskDefsOnboardingInit(): array {
    return [
      'marvin_product.init' => [
        'weight' => -999,
        // @todo Native task.
        'task' => function (RoboState $state): int {
          $composerInfo = $this->getComposerInfo();
          $composerInfo->getDrupalRootDir();
          $state['cwd'] = getcwd();
          $state['projectRoot'] = Path::getDirectory($composerInfo->getJsonFileName());
          $state['isDeveloperMode'] = $this->isDeveloperMode($state['projectRoot']);
          if ($state['cwd'] === $state['projectRoot']) {
            $state['projectRoot'] = '.';
          }
          $state['drupalRoot'] = $composerInfo->getDrupalRootDir();
          $state['siteDirs'] = MarvinUtils::collectDrupalSiteDirs("{$state['projectRoot']}/{$state['drupalRoot']}");
          $state['runtimeEnvironments'] = $this->getRuntimeEnvironments();
          $state['runtimeEnvironment'] = $this->getCurrentRuntimeEnvironment();
          $state['primaryUri'] = $this->getLocalUri();

          return 0;
        },
      ],
    ];
  }

  protected function getTaskDefsOnboardingCreateRequiredDirs(): array {
    $taskForEach = $this->taskForEach();
    $taskForEach
      ->iterationMessage('Create required directories for site: {key}')
      ->deferTaskConfiguration('setIterable', 'siteDirs')
      ->withBuilder(function (CollectionBuilder $builder, string $key, $siteDir) use ($taskForEach): void {
        if (!($siteDir instanceof \SplFileInfo)) {
          $builder->addCode(function (): int {
            return 0;
          });

          return;
        }

        /** @var \Symfony\Component\Finder\SplFileInfo $siteDir */
        $state = $taskForEach->getState();
        $projectRoot = $state['projectRoot'];
        $drupalRoot = $state['drupalRoot'];
        $site = $siteDir->getBasename();

        $builder
          ->addTask(
            // @todo Get these directory paths from the actual configuration.
            $this
              ->taskFilesystemStack()
              ->mkdir("$projectRoot/$drupalRoot/sites/$site/files")
              ->mkdir("$projectRoot/sites/all/translations")
              ->mkdir("$projectRoot/sites/$site/config/prod")
              ->mkdir("$projectRoot/sites/$site/php_storage")
              ->mkdir("$projectRoot/sites/$site/private")
              ->mkdir("$projectRoot/sites/$site/temporary")
              ->mkdir("$projectRoot/sites/$site/backup")
          );
      });

    return [
      'marvin_product.create_required_directories' => [
        'weight' => 100,
        'task' => $taskForEach,
      ],
    ];
  }

  protected function getTaskDefsOnboardingHashSaltTxt(): array {
    $taskForEach = $this->taskForEach();
    $taskForEach
      ->iterationMessage('Create hash_salt.txt file for site: {key}')
      ->deferTaskConfiguration('setIterable', 'siteDirs')
      ->withBuilder($this->getTaskBuilderOnboardingHashSaltTxt($taskForEach));

    return [
      'marvin_product.create_hash_salt_txt' => [
        'weight' => 102,
        'task' => $taskForEach,
      ],
    ];
  }

  protected function getTaskDefsOnboardingSettingsLocalPhp(): array {
    $taskForEach = $this->taskForEach();
    $taskForEach
      ->iterationMessage('Create settings.host.php for site: {key}')
      ->deferTaskConfiguration('setIterable', 'siteDirs')
      ->withBuilder(function (CollectionBuilder $builder, string $key, $siteDir) use ($taskForEach): void {
        if (!($siteDir instanceof \SplFileInfo)) {
          $builder->addCode(function (): int {
            return 0;
          });

          return;
        }

        /** @var \Symfony\Component\Finder\SplFileInfo $siteDir */
        $state = $taskForEach->getState();
        $projectRoot = $state['projectRoot'];
        $drupalRoot = $state['drupalRoot'];
        $site = $siteDir->getBasename();

        $builder->addCode(function () use ($projectRoot, $drupalRoot, $site) {
          $logger = $this->getLogger();
          $dst = "$projectRoot/$drupalRoot/sites/$site/settings.host.php";
          if ($this->fs->exists($dst)) {
            $logger->debug(
              'File "<info>{fileName}</info>" already exists',
              [
                'fileName' => $dst,
              ],
            );

            return 0;
          }

          $src = $this->getExampleSettingsLocalPhp($projectRoot, $drupalRoot, $site);
          if (!$src) {
            $logger->debug('There is no source for "settings.host.php"');

            return 0;
          }

          $result = $this
            ->taskFilesystemStack()
            ->copy($src, $dst)
            ->run();

          return $result->wasSuccessful() ? 0 : 1;
        });
      });

    return [
      'marvin_product.settings_local_php' => [
        'weight' => 101,
        'task' => $taskForEach,
      ],
    ];
  }

  protected function getTaskDefsOnboardingDrushHostYml(): array {
    return [
      'marvin_product.drush_host_yml' => [
        'weight' => 103,
        'task' => function (RoboState $state): int {
          $logger = $this->getLogger();
          $hostFilePath = "{$state['projectRoot']}/drush/drush.host.yml";
          $exampleFilePath = "{$state['projectRoot']}/drush/drush.local.example.yml";
          $loggerArgs = [
            'hostFilePath' => $hostFilePath,
            'exampleFilePath' => $exampleFilePath,
          ];

          $runtimeEnvironment = $state['runtimeEnvironment'];
          if ($runtimeEnvironment['id'] !== 'host') {
            $logger->info(
              '{hostFilePath} skipped because there is no "host" runtime environment',
              $loggerArgs,
            );

            return 0;
          }

          if ($this->fs->exists($hostFilePath)) {
            $logger->info(
              'update option.uri in {hostFilePath}',
              $loggerArgs,
            );
            $content = MarvinUtils::fileGetContents($hostFilePath);
          }
          elseif ($this->fs->exists($exampleFilePath)) {
            $logger->info(
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
              MarvinUtils::fileGetContents($exampleFilePath),
            );
          }
          else {
            $logger->info(
              'create {hostFilePath} with default content',
              $loggerArgs,
            );
            $content = $this->getDrushLocalYmlContent();
          }

          $uri = $state['primaryUri'];
          if ($uri === '${options.uri}') {
            $uri = $this->getLocalUri();
          }

          $content = preg_replace(
            '/^ {2}uri: .+$/um',
            '  uri: ' . MarvinUtils::escapeYamlValueString($uri),
            $content,
          );

          $this->fs->dumpFile($hostFilePath, $content);

          return 0;
        },
      ],
    ];
  }

  protected function getTaskDefsOnboardingRteSwitch(): array {
    return [
      'marvin_product.runtime_environment.switch' => [
        'weight' => 999,
        'task' => $this->delegate('runtime-environment:switch', $this->getCurrentRuntimeEnvironment()),
      ],
    ];
  }

  protected function getTaskBuilderOnboardingHashSaltTxt($taskForEach): \Closure {
    return function (CollectionBuilder $builder, string $key, $siteDir) use ($taskForEach): void {
      if (!($siteDir instanceof \SplFileInfo)) {
        $builder->addCode(function (): int {
          return 0;
        });

        return;
      }

      $state = $taskForEach->getState();
      $projectRoot = $state['projectRoot'];

      $task = $this->getTaskOnboardingHashSaltTxtSingle($projectRoot, $siteDir);
      if ($task instanceof \Closure) {
        $builder->addCode($task);
      }
      else {
        $builder->addTask($task);
      }
    };
  }

  /**
   * @return \Closure|\Robo\Contract\TaskInterface
   */
  protected function getTaskOnboardingHashSaltTxtSingle(string $projectRoot, \SplFileInfo $siteDir) {
    return function () use ($projectRoot, $siteDir): int {
      $site = $siteDir->getBasename();
      $filePath = "$projectRoot/sites/$site/hash_salt.txt";
      $loggerArgs = [
        'filePath' => $filePath,
      ];
      if ($this->fs->exists($filePath)) {
        $this->getLogger()->info(
          'File "<info>{filePath}</info>" already exists',
          $loggerArgs,
        );

        return 0;
      }

      $this->getLogger()->info(
        'Crate file "<info>{filePath}</info>"',
        $loggerArgs,
      );
      $result = $this
        ->taskWriteToFile($filePath)
        ->text(MarvinUtils::generateHashSalt())
        ->run();

      if ($result->wasSuccessful()) {
        return 0;
      }

      $loggerArgs['errorMessage'] = $result->getMessage();
      $this->getLogger()->error(
        'Crate file "<info>{filePath}</info>" failed. {errorMessage}',
        $loggerArgs,
      );

      return 1;
    };
  }

  protected function getExampleSettingsLocalPhp(string $projectRoot, string $drupalRoot, string $site): ?string {
    $fileNames = [
      "$projectRoot/$drupalRoot/sites/$site/settings.local.example.php",
      "$projectRoot/$drupalRoot/sites/$site/example.settings.local.php",
      "$projectRoot/$drupalRoot/sites/example.settings.local.php",
    ];

    foreach ($fileNames as $fileName) {
      if ($this->fs->exists($fileName)) {
        return $fileName;
      }
    }

    return NULL;
  }

  protected function getLocalUri(): string {
    if ($this->input()->getOption('uri')) {
      return $this->input()->getOption('uri');
    }

    $composerInfo = $this->getComposerInfo();

    return sprintf(
      'https://%s.%s.localhost',
      (new UnicodeString($composerInfo->packageName))
        ->snake()
        ->replace('_', '-')
        ->toString(),
      (new UnicodeString($composerInfo->packageVendor))
        ->snake()
        ->replace('_', '-')
        ->toString(),
    );
  }

  protected function isDeveloperMode(string $projectRoot): bool {
    // @todo Read the tests dir path from configuration.
    return $this->fs->exists("$projectRoot/tests");
  }

  protected function getDrushLocalYmlContent(): string {
    return <<<'YAML'
options:
  uri: 'APP_PRIMARY_URI'

marvin:
  runtime_environments:
    host:
      enabled: true
YAML;
  }

}
