<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\Robo\VersionNumberTaskLoader;
use Drupal\marvin\Utils as MarvinUtils;
use Drush\Commands\marvin\Artifact\ArtifactBuildCommandsBase;
use Robo\Collection\CollectionBuilder;
use Robo\State\Data as RoboStateData;
use Sweetchuck\Robo\Git\GitTaskLoader;
use Symfony\Component\Console\Input\InputInterface;
use Webmozart\PathUtil\Path;

class ArtifactBuildCommands extends ArtifactBuildCommandsBase {

  use VersionNumberTaskLoader;
  use GitTaskLoader;

  /**
   * @var string[]
   *
   * @todo This should be available from everywhere.
   */
  protected $versionPartNames = [
    'major',
    'minor',
    'patch',
    'pre-release',
    'meta-data',
  ];

  /**
   * @var string
   */
  protected $defaultVersionPartToBump = 'minor';

  /**
   * @hook on-event marvin:artifact:types
   */
  public function onEventMarvinArtifactTypes(string $projectType): array {
    $types = [];

    if ($projectType === 'product') {
      $types['vanilla'] = [
        'label' => dt('Vanilla'),
        'description' => dt('Not customized'),
      ];
    }

    return $types;
  }

  /**
   * @command marvin:artifact:build:vanilla
   * @bootstrap none
   *
   * @todo Validate "version-bump" option.
   */
  public function artifactBuildVanilla(
    array $options = [
      'version-bump' => 'minor',
    ]
  ): CollectionBuilder {
    return $this->delegate('vanilla');
  }

  /**
   * @hook on-event marvin:artifact:build
   */
  public function onEventMarvinArtifactBuild(InputInterface $input): array {
    return $this->getStepsBuildVanilla(
      '.',
      $this->getConfig()->get('command.marvin.settings.artifactDir'),
      $input->getOption('version-bump')
    );
  }

  /**
   * @hook on-event marvin:artifact:build:vanilla
   */
  public function onEventMarvinArtifactBuildVanilla(InputInterface $input): array {
    return $this->getStepsBuildVanilla(
      '.',
      $this->getConfig()->get('command.marvin.settings.artifactDir'),
      $input->getOption('version-bump')
    );
  }

  protected function getStepsBuildVanilla(
    string $srcDir,
    string $artifactDir,
    string $versionPartToBump
  ): array {
    return [
      'marvin.initStateData' => [
        'weight' => -240,
        'task' => function (RoboStateData $data) use ($versionPartToBump) : int {
          $data['coreVersion'] = '8.x';
          $data['artifactType'] = 'vanilla';
          $data['versionPartToBump'] = $versionPartToBump;

          return 0;
        },
      ],
      'marvin.detectLatestVersionNumber' => [
        'weight' => -230,
        'task' => $this->getTaskDetectLatestVersionNumber(),
      ],
      'marvin.composeNextVersionNumber' => [
        'weight' => -220,
        'task' => $this->getTaskComposeNextVersionNumber(),
      ],
      'marvin.composeBuildDirPath' => [
        'weight' => -210,
        'task' => $this->getTaskComposeBuildDir($artifactDir),
      ],
      'marvin.prepareDirectory' => [
        'weight' => -200,
        'task' => $this
          ->taskMarvinPrepareDirectory()
          ->deferTaskConfiguration('setWorkingDirectory', 'buildDir'),
      ],
      'marvin.collectFiles' => [
        'weight' => -190,
        'task' => $this
          ->taskMarvinArtifactCollectFiles()
          ->setPackagePath($srcDir),
      ],
      'marvin.copyFiles' => [
        'weight' => -180,
        'task' => $this
          ->taskMarvinCopyFiles()
          ->setSrcDir($srcDir)
          ->deferTaskConfiguration('setDstDir', 'buildDir')
          ->deferTaskConfiguration('setFiles', 'files'),
      ],
      'marvin.bumpVersionNumber.root' => [
        'weight' => 200,
        'task' => $this
          ->taskMarvinVersionNumberBumpExtensionInfo()
          ->setBumpExtensionInfo(FALSE)
          ->deferTaskConfiguration('setPackagePath', 'buildDir')
          ->deferTaskConfiguration('setVersionNumber', 'nextVersionNumber.drupal'),
      ],
      'marvin.collectCustomExtensionDirs' => [
        'weight' => 210,
        'task' => $this->getTaskCollectCustomExtensionDirs(),
      ],
      'marvin.bumpVersionNumber.extensions' => [
        'weight' => 220,
        'task' => $this->getTaskBumpVersionNumberExtensions('customExtensionDirs', 'nextVersionNumber.drupal'),
      ],
    ];
  }

  protected function getTaskDetectLatestVersionNumber(): \Closure {
    return function (RoboStateData $data): int {
      // @todo Detect latest version number.
      $data['latestVersionNumber.semver'] = '1.0.0';

      return 0;
    };
  }

  protected function getTaskComposeNextVersionNumber(): \Closure {
    return function (RoboStateData $data): int {
      $data['nextVersionNumber.semver'] = NULL;
      $data['nextVersionNumber.drupal'] = NULL;

      $versionPartToBump = $data['versionPartToBump'] ?? $this->defaultVersionPartToBump;
      if (!in_array($versionPartToBump, $this->versionPartNames)) {
        $data['nextVersionNumber.semver'] = $versionPartToBump;
      }

      if (!$data['nextVersionNumber.semver']) {
        $data['nextVersionNumber.semver'] = (string) MarvinUtils::incrementSemVersion(
          $data['latestVersionNumber.semver'] ?? '0.0.0',
          $versionPartToBump
        );
      }

      if ($data['nextVersionNumber.semver']) {
        $data['nextVersionNumber.drupal'] = MarvinUtils::semverToDrupal(
          $data['coreVersion'],
          $data['nextVersionNumber.semver']
        );
      }

      return 0;
    };
  }

  protected function getTaskComposeBuildDir(string $artifactDir): \Closure {
    return function (RoboStateData $data) use ($artifactDir): int {
      $data['buildDir'] = "$artifactDir/{$data['nextVersionNumber.semver']}/{$data['artifactType']}";

      return 0;
    };
  }

  protected function getTaskCollectCustomExtensionDirs(): \Closure {
    return function (RoboStateData $data): int {
      $drupalRootDir = MarvinUtils::detectDrupalRootDir($this->getComposerInfo());

      $result = $this
        ->taskGitListFiles()
        ->setPaths([
          "$drupalRootDir/modules/custom/*/*.info.yml",
          "$drupalRootDir/profiles/custom/*/*.info.yml",
          "$drupalRootDir/themes/custom/*/*.info.yml",
        ])
        ->run()
        ->stopOnFail();

      $buildDir = $data['buildDir'];
      $data['customExtensionDirs'] = [];
      /** @var \Sweetchuck\Robo\Git\ListFilesItem $file */
      foreach ($result['files'] as $file) {
        $data['customExtensionDirs'][] = Path::join($buildDir, Path::getDirectory($file->fileName));
      }

      return 0;
    };
  }

  protected function getTaskBumpVersionNumberExtensions(
    string $iterableStateKey,
    string $versionStateKey
  ): CollectionBuilder {
    $forEachTask = $this->taskForEach();

    $forEachTask
      ->deferTaskConfiguration('setIterable', $iterableStateKey)
      ->withBuilder(function (
          CollectionBuilder $builder,
          $key,
          string $extensionDir
        ) use (
          $forEachTask,
          $versionStateKey
        ) {

        if (!file_exists($extensionDir)) {
          return;
        }

        $builder->addTask(
          $this
            ->taskMarvinVersionNumberBumpExtensionInfo()
            ->setBumpComposerJson(FALSE)
            ->setPackagePath($extensionDir)
            ->setVersionNumber($forEachTask->getState()->offsetGet($versionStateKey))
        );
      });

    return $forEachTask;
  }

}
