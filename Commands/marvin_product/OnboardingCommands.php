<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\ComposerInfo;
use Drupal\marvin\Utils as MarvinUtils;
use Drush\Commands\marvin\CommandsBase;
use Robo\Collection\CollectionBuilder;
use Robo\Contract\CommandInterface;
use Robo\State\Data as RoboState;
use Stringy\StaticStringy;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

class OnboardingCommands extends CommandsBase {

  protected Filesystem $fs;

  public function __construct(?ComposerInfo $composerInfo = NULL, ?Filesystem $fs = NULL) {
    parent::__construct($composerInfo);
    $this->fs = $fs ?: new Filesystem();
  }

  /**
   * @hook on-event marvin:composer:post-install-cmd
   */
  public function onEventMarvinComposerPostInstallCmd(
    InputInterface $input,
    OutputInterface $output,
    string $projectRoot
  ): array {
    return $this->onEventMarvinOnboarding();
  }

  /**
   * @hook on-event marvin:onboarding
   */
  public function onEventMarvinOnboarding(): array {
    return $this->getTaskDefsOnboardingInit()
      + $this->getTaskDefsOnboardingCreateRequiredDirs()
      + $this->getTaskDefsOnboardingSettingsLocalPhp()
      + $this->getTaskDefsOnboardingDrushLocalYml()
      + $this->getTaskDefsOnboardingHashSaltTxt();
  }

  /**
   * Setup the required files and configuration.
   *
   * @command marvin:onboarding
   *
   * @bootstrap none
   */
  public function cmdMarvinOnboardingExecute(array $options = []): CommandInterface {
    return $this->delegate('onboarding');
  }

  protected function getTaskDefsOnboardingInit(): array {
    return [
      'marvin_product.init' => [
        'weight' => -999,
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
          $builder->addCode(function (): int { return 0; });

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

  protected function getTaskDefsOnboardingSettingsLocalPhp(): array {
    $taskForEach = $this->taskForEach();
    $taskForEach
      ->iterationMessage('Create settings.host.php for site: {key}')
      ->deferTaskConfiguration('setIterable', 'siteDirs')
      ->withBuilder(function (CollectionBuilder $builder, string $key, $siteDir) use ($taskForEach): void {
        if (!($siteDir instanceof \SplFileInfo)) {
          $builder->addCode(function (): int { return 0; });

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

  protected function getTaskDefsOnboardingDrushLocalYml(): array {
    return [
      'marvin_product.drush_local_yml' => [
        'weight' => 103,
        'task' => function (RoboState $state): int {
          $localFileName = "{$state['projectRoot']}/drush/drush.host.yml";
          if ($this->fs->exists($localFileName)) {
            $this->getLogger()->debug(
              'File "<info>{fileName}</info>" already exists',
              ['fileName' => $localFileName]
            );

            return 0;
          }

          $exampleFileName = "{$state['projectRoot']}/drush/drush.local.example.yml";
          if ($this->fs->exists($exampleFileName)) {
            $this->fs->copy($exampleFileName, $localFileName);
          }

          $localChanged = FALSE;
          $localContent = Yaml::parseFile($localFileName);

          $baseFileName = "{$state['projectRoot']}/drush/drush.app.yml";
          $baseContent = [];
          if ($this->fs->exists($baseFileName)) {
            $baseContent = Yaml::parseFile($baseFileName);
          }

          $uri = $this->getLocalUri();
          $baseUri = $baseContent['options']['uri'] ?? NULL;
          if ($baseUri != $uri) {
            $localContent['options']['uri'] = $uri;
            $localChanged = TRUE;
          }

          if ($localChanged) {
            $this->fs->dumpFile(
              $localFileName,
              Yaml::dump($localContent, 42, 2)
            );
          }

          return 0;
        },
      ],
    ];
  }

  protected function getTaskDefsOnboardingHashSaltTxt(): array {
    $taskForEach = $this->taskForEach();
    $taskForEach
      ->iterationMessage('Create hash_salt.txt file for site: {key}')
      ->deferTaskConfiguration('setIterable', 'siteDirs')
      ->withBuilder(function (CollectionBuilder $builder, string $key, $siteDir) use ($taskForEach): void {
        if (!($siteDir instanceof \SplFileInfo)) {
          $builder->addCode(function (): int { return 0; });

          return;
        }

        $state = $taskForEach->getState();
        $projectRoot = $state['projectRoot'];

        $builder->addCode(function () use ($projectRoot, $siteDir): int {
          $site = $siteDir->getBasename();
          $fileName = "$projectRoot/sites/$site/hash_salt.txt";
          if ($this->fs->exists($fileName)) {
            $this->getLogger()->debug(
              'File "<info>{fileName}</info>" already exists',
              [
                'fileName' => $fileName,
              ],
            );

            return 0;
          }

          $result = $this
            ->taskWriteToFile($fileName)
            ->text(MarvinUtils::generateHashSalt())
            ->run();

          // @todo Error handling.
          return $result->wasSuccessful() ? 0 : 1;
        });
      });

    return [
      'marvin_product.create_hash_salt_txt' => [
        'weight' => 102,
        'task' => $taskForEach,
      ],
    ];
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
    if ($this->input()->getOption('url')) {
      return $this->input()->getOption('url');
    }

    $composerInfo = $this->getComposerInfo();

    return sprintf('http://%s.localhost', StaticStringy::dasherize($composerInfo->packageName));
  }

  protected function isDeveloperMode(string $projectRoot): bool {
    // @todo Read the tests dir path from configuration.
    return $this->fs->exists("$projectRoot/tests");
  }

}
