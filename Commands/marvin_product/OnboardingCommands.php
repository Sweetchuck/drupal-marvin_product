<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\ComposerInfo;
use Drupal\marvin\Utils as MarvinUtils;
use Drush\Commands\marvin\CommandsBase;
use Robo\Collection\CollectionBuilder;
use Robo\Contract\TaskInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class OnboardingCommands extends CommandsBase {

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

  protected function getTaskOnboarding(string$projectRoot, string $siteDir): CollectionBuilder {
    $composerInfo = $this->getComposerInfo();

    if (!empty($composerInfo['scripts']['post-create-project-cmd'])) {
      return $this
        ->collectionBuilder()
        ->addCode(function (): int {
          $this->output()->writeln('Onboarding is skipped, because this project is still in template phase.');

          return 0;
        });
    }

    $drupalRoot = MarvinUtils::detectDrupalRootDir($composerInfo);

    return $this
      ->collectionBuilder()
      ->addTask($this->getTaskOnboardingCreateRequiredDirs($projectRoot, $drupalRoot, $siteDir))
      ->addCode($this->getTaskOnboardingSettingsLocalPhp($projectRoot, $drupalRoot, $siteDir))
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
      $src = $this->getExampleSettingsLocalPhp($projectRoot, $drupalRoot, $siteDir);
      $dst = "$projectRoot/$drupalRoot/sites/$siteDir/settings.local.php";
      if (!$src || $this->fs->exists($dst)) {
        return 0;
      }

      $this
        ->taskFilesystemStack()
        ->copy($src, $dst)
        ->run()
        ->stopOnFail();

      return 0;
    };
  }

  protected function getTaskOnboardingHashSaltTxt(string $projectRoot, string $siteDir): \Closure {
    return function () use ($projectRoot, $siteDir) {
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
    return uniqid(mt_rand(), TRUE);
  }

}
