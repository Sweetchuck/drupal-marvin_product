<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\ComposerInfo;
use Drupal\marvin\Utils as MarvinUtils;
use Drush\Commands\marvin\CommandsBase;
use Robo\Collection\CollectionBuilder;
use Robo\Contract\TaskInterface;
use Stringy\StaticStringy;
use Sweetchuck\Robo\Git\GitTaskLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

class OnboardingCommands extends CommandsBase {

  use GitTaskLoader;

  /**
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fs;

  public function __construct(?ComposerInfo $composerInfo = NULL, ?Filesystem $fs = NULL) {
    parent::__construct($composerInfo);
    $this->fs = $fs ?: new Filesystem();
  }

  /**
   * @hook on-event marvin:composer:post-install-cmd
   */
  public function composerPostInstallCmd(InputInterface $input, OutputInterface $output, string $projectRoot): array {
    $tasks = [];

    if ($input->getOption('dev-mode')) {
      $tasks['marvin.onboarding'] = [
        'weight' => 0,
        'task' => $this->getTaskOnboarding($projectRoot, 'default'),
      ];
    }

    return $tasks;
  }

  /**
   * @command marvin:onboarding
   */
  public function onboarding(): CollectionBuilder {
    return $this->getTaskOnboarding(getcwd(), 'default');
  }

  protected function getTaskOnboarding(string $projectRoot, string $siteDir): CollectionBuilder {
    $composerInfo = $this->getComposerInfo();

    if (!empty($composerInfo['scripts']['post-create-project-cmd'])) {
      return $this
        ->collectionBuilder()
        ->addCode(function (): int {
          $this->getLogger()->debug('Onboarding is skipped, because this project is still in template phase.');

          return 0;
        });
    }

    $drupalRoot = MarvinUtils::detectDrupalRootDir($composerInfo);

    return $this
      ->collectionBuilder()
      ->addTask($this->getTaskOnboardingCreateRequiredDirs($projectRoot, $drupalRoot, $siteDir))
      ->addCode($this->getTaskOnboardingSettingsLocalPhp($projectRoot, $drupalRoot, $siteDir))
      ->addTask($this->getTaskOnboardingBehatLocalYml())
      ->addCode($this->getTaskOnboardingDrushLocalYml($projectRoot))
      ->addCode($this->getTaskOnboardingHashSaltTxt($projectRoot, $siteDir));
  }

  protected function getTaskOnboardingCreateRequiredDirs(string $projectRoot, string $drupalRoot, string $siteDir): TaskInterface {
    return $this
      ->taskFilesystemStack()
      ->mkdir("$projectRoot/$drupalRoot/sites/$siteDir/files")
      ->mkdir("$projectRoot/sites/all/translations")
      ->mkdir("$projectRoot/sites/$siteDir/config/sync")
      ->mkdir("$projectRoot/sites/$siteDir/private")
      ->mkdir("$projectRoot/sites/$siteDir/temporary")
      ->mkdir("$projectRoot/sites/$siteDir/backup");
  }

  protected function getTaskOnboardingSettingsLocalPhp(string $projectRoot, string $drupalRoot, string $siteDir): \Closure {
    return function () use ($projectRoot, $drupalRoot, $siteDir) {
      $dst = "$projectRoot/$drupalRoot/sites/$siteDir/settings.local.php";
      if ($this->fs->exists($dst)) {
        $this->getLogger()->debug('"settings.local.php" already exists');

        return 0;
      }

      $src = $this->getExampleSettingsLocalPhp($projectRoot, $drupalRoot, $siteDir);
      if (!$src) {
        $this->getLogger()->debug('There is no source for "settings.local.php"');

        return 0;
      }

      $result = $this
        ->taskFilesystemStack()
        ->copy($src, $dst)
        ->run();

      return $result->wasSuccessful() ? 0 : 1;
    };
  }

  protected function getTaskOnboardingHashSaltTxt(string $projectRoot, string $siteDir): \Closure {
    return function () use ($projectRoot, $siteDir): int {
      $fileName = "$projectRoot/sites/$siteDir/hash_salt.txt";
      if ($this->fs->exists($fileName)) {
        return 0;
      }

      $this
        ->taskWriteToFile($fileName)
        ->text($this->generateHashSalt())
        ->run()
        ->stopOnFail();

      return 0;
    };
  }

  protected function getTaskOnboardingBehatLocalYml(): CollectionBuilder {
    return $this
      ->collectionBuilder()
      ->addTask($this
        ->taskGitListFiles()
        ->setPaths(['behat.yml', '*/behat.yml'])
      )
      ->addTask($this
        ->taskForEach()
        ->deferTaskConfiguration('setIterable', 'files')
        ->withBuilder(function (CollectionBuilder $builder, string $baseFileName) {
          $builder->addCode($this->getTaskOnboardingBehatLocalYmlSingle($baseFileName));
        })
      );
  }

  protected function getTaskOnboardingBehatLocalYmlSingle(string $baseFileName): \Closure {
    return function () use ($baseFileName): int {
      $behatDir = Path::getDirectory($baseFileName);
      $exampleFileName = "$behatDir/behat.local.example.yml";
      $localFileName = "$behatDir/behat.local.yml";

      if ($this->fs->exists($exampleFileName)) {
        $this->fs->copy($exampleFileName, $localFileName);

        return 0;
      }

      $this->fs->dumpFile($localFileName, '{}');

      return 0;
    };
  }

  protected function getTaskOnboardingDrushLocalYml(string $projectRoot): \Closure {
    return function () use ($projectRoot): int {
      $localFileName = "$projectRoot/drush/drush.local.yml";
      if ($this->fs->exists($localFileName)) {
        $this->getLogger()->debug(
          'File "<info>{fileName}</info>" already exists',
          ['fileName' => $localFileName]
        );

        return 0;
      }

      $exampleFileName = "$projectRoot/drush/drush.local.example.yml";
      if ($this->fs->exists($exampleFileName)) {
        $this->fs->copy($exampleFileName, $localFileName);

        return 0;
      }

      $baseFileName = "$projectRoot/drush/drush.yml";
      if (!$this->fs->exists($baseFileName)) {
        return 0;
      }

      $uri = $this->getLocalUri();
      $baseContent = Yaml::parseFile($baseFileName);
      if (isset($baseContent['command']['options']['uri'])
        && $baseContent['command']['options']['uri'] === $uri
      ) {
        return 0;
      }

      $localContent = [
        'command' => [
          'options' => [
            'uri' => $uri,
          ],
        ],
      ];

      $this->fs->dumpFile(
        $localFileName,
        Yaml::dump($localContent, 42, 2)
      );

      return 0;
    };
  }

  protected function getExampleSettingsLocalPhp(string $projectRoot, string $drupalRoot, string $siteDir): ?string {
    $fileNames = [
      "$projectRoot/$drupalRoot/sites/$siteDir/example.settings.local.php",
      "$projectRoot/$drupalRoot/sites/example.settings.local.php",
    ];

    foreach ($fileNames as $fileName) {
      if ($this->fs->exists($fileName)) {
        return $fileName;
      }
    }

    return NULL;
  }

  protected function generateHashSalt(): string {
    return uniqid('', TRUE);
  }

  protected function fileGetContents(string $fileName): string {
    $content = file_get_contents($fileName);
    if ($content === FALSE) {
      throw new \Exception('@todo');
    }

    return $content;
  }

  protected function getLocalUri(): string {
    $composerInfo = $this->getComposerInfo();

    return sprintf('http://%s.localhost', StaticStringy::dasherize($composerInfo->packageName));
  }

}
