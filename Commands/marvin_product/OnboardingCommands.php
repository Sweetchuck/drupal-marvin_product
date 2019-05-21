<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Closure;
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
      // @todo Detect all the $docroot/sites/*/settings.php.
      $tasks['marvin.onboarding'] = [
        'weight' => 0,
        'task' => $this->getTaskOnboarding($projectRoot, 'default'),
      ];
    }

    return $tasks;
  }

  /**
   * @command marvin:onboarding
   * @bootstrap none
   */
  public function onboarding(
    array $options = [
      'url' => '',
    ]
  ): CollectionBuilder {
    $projectRoot = Path::getDirectory($this->getComposerInfo()->getJsonFileName());

    // @todo Bootstrap level config and get the $siteDir.
    return $this->getTaskOnboarding($projectRoot, 'default');
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

    $isDeveloperMode = $this->isDeveloperMode($projectRoot);
    $drupalRoot = MarvinUtils::detectDrupalRootDir($composerInfo);

    $cb = $this->collectionBuilder();

    $cb->addTask($this->getTaskOnboardingCreateRequiredDirs($projectRoot, $drupalRoot, $siteDir));
    $cb->addCode($this->getTaskOnboardingSettingsLocalPhp($projectRoot, $drupalRoot, $siteDir));

    if ($isDeveloperMode) {
      $cb->addTask($this->getTaskOnboardingBehatLocalYml());
    }

    $cb->addCode($this->getTaskOnboardingDrushLocalYml($projectRoot));
    $cb->addCode($this->getTaskOnboardingHashSaltTxt($projectRoot, $siteDir));

    return $cb;
  }

  protected function getTaskOnboardingCreateRequiredDirs(string $projectRoot, string $drupalRoot, string $siteDir): TaskInterface {
    return $this
      ->taskFilesystemStack()
      ->mkdir("$projectRoot/$drupalRoot/sites/$siteDir/files")
      ->mkdir("$projectRoot/sites/all/translations")
      ->mkdir("$projectRoot/sites/$siteDir/config/sync")
      ->mkdir("$projectRoot/sites/$siteDir/php_storage")
      ->mkdir("$projectRoot/sites/$siteDir/private")
      ->mkdir("$projectRoot/sites/$siteDir/temporary")
      ->mkdir("$projectRoot/sites/$siteDir/backup");
  }

  protected function getTaskOnboardingSettingsLocalPhp(string $projectRoot, string $drupalRoot, string $siteDir): Closure {
    return function () use ($projectRoot, $drupalRoot, $siteDir) {
      $logger = $this->getLogger();
      $dst = "$projectRoot/$drupalRoot/sites/$siteDir/settings.local.php";
      if ($this->fs->exists($dst)) {
        $logger->debug(
          'File "<info>{fileName}</info>" already exists',
          ['fileName' => $dst]
        );

        return 0;
      }

      $src = $this->getExampleSettingsLocalPhp($projectRoot, $drupalRoot, $siteDir);
      if (!$src) {
        $logger->debug('There is no source for "settings.local.php"');

        return 0;
      }

      $result = $this
        ->taskFilesystemStack()
        ->copy($src, $dst)
        ->run();

      return $result->wasSuccessful() ? 0 : 1;
    };
  }

  protected function getTaskOnboardingHashSaltTxt(string $projectRoot, string $siteDir): Closure {
    return function () use ($projectRoot, $siteDir): int {
      $fileName = "$projectRoot/sites/$siteDir/hash_salt.txt";
      if ($this->fs->exists($fileName)) {
        $this->getLogger()->debug(
          'File "<info>{fileName}</info>" already exists',
          ['fileName' => $fileName]
        );

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

  protected function getTaskOnboardingBehatLocalYmlSingle(string $baseFileName): Closure {
    return function () use ($baseFileName): int {
      $behatDir = Path::getDirectory($baseFileName);
      $exampleFileName = "$behatDir/behat.local.example.yml";
      $localFileName = "$behatDir/behat.local.yml";

      if ($this->fs->exists("$localFileName")) {
        $this->getLogger()->debug(
          'File "<info>{fileName}</info>" already exists',
          ['fileName' => $localFileName]
        );

        return 0;
      }

      $localFileContent = <<<YAML
default:
  extensions:
    Behat\MinkExtension:
      base_url: 'http://localhost'

YAML;

      if ($this->fs->exists($exampleFileName)) {
        $localFileContent = $this->fileGetContents($exampleFileName);
      }

      // @todo This is not bullet proof.
      $localFileContent = preg_replace(
        '/(?<=\n      base_url:).*?(?=\n)/u',
        ' ' . MarvinUtils::escapeYamlValueString($this->getLocalUri()),
        $localFileContent,
        -1,
        $count
      );

      $this->fs->dumpFile($localFileName, $localFileContent);

      return 0;
    };
  }

  protected function getTaskOnboardingDrushLocalYml(string $projectRoot): Closure {
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
      "$projectRoot/$drupalRoot/sites/$siteDir/settings.local.example.php",
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
    return bin2hex(random_bytes($this->getHashSaltLength()));
  }

  protected function getHashSaltLength(): int {
    return random_int(32, 64);
  }

  protected function fileGetContents(string $fileName): string {
    $content = file_get_contents($fileName);
    if ($content === FALSE) {
      throw new \Exception('@todo');
    }

    return $content;
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
