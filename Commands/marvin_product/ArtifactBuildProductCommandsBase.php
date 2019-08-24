<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\ComposerInfo;
use Drush\Commands\marvin\ArtifactBuildCommandsBase;
use Robo\State\Data as RoboStateData;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

abstract class ArtifactBuildProductCommandsBase extends ArtifactBuildCommandsBase {

  /**
   * @var string
   */
  protected $drupalRootDir = '';

  /**
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fs;

  /**
   * @var int
   */
  protected $jsonEncodeFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

  public function __construct(?ComposerInfo $composerInfo = NULL, ?Filesystem $fs = NULL) {
    parent::__construct($composerInfo);

    $this->fs = $fs ?: new Filesystem();
  }

  /**
   * {@inheritdoc}
   */
  protected function isApplicable(string $projectType): bool {
    return $projectType === 'product';
  }

  /**
   * {@inheritdoc}
   */
  protected function getBuildSteps(): array {
    return parent::getBuildSteps() + [
      'resolveRelativePackagePaths.marvin_product' => [
        'weight' => $this->incrementBuildStepWeight(),
        'task' => $this->getTaskResolveRelativePackagePaths(),
      ],
      'moveDocroot.marvin_product' => [
        'weight' => $this->incrementBuildStepWeight(),
        'task' => $this->getTaskMoveDocroot(),
      ],
      'composerUpdate.marvin_product' => [
        'weight' => $this->incrementBuildStepWeight(),
        'task' => $this->getTaskComposerUpdate(),
      ],
      'gitignoreEntries.marvin_product' => [
        'weight' => $this->incrementBuildStepWeight(),
        'task' => $this->getTaskGitIgnoreEntries(),
      ],
      'gitignoreDump.marvin_product' => [
        'weight' => $this->incrementBuildStepWeight(),
        'task' => $this->getTaskGitIgnoreDump(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitialStateData(): array {
    $data = parent::getInitialStateData();

    /** @var \Drupal\marvin\ComposerInfo $composerInfo */
    $composerInfo = $data['composerInfo'];
    $data['oldDrupalRootDir'] = $composerInfo->getDrupalRootDir();
    $data['newDrupalRootDir'] = $this->drupalRootDir ?: $data['oldDrupalRootDir'];

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskCollectChildExtensionDirs() {
    return function (RoboStateData $data): int {
      /** @var \Drupal\marvin\ComposerInfo $composerInfo */
      $composerInfo = $data['composerInfo'];
      $drupalRootDir = $composerInfo->getDrupalRootDir();

      $result = $this
        ->taskGitListFiles()
        ->setPaths([
          "$drupalRootDir/modules/custom/*/*.info.yml",
          "$drupalRootDir/profiles/custom/*/*.info.yml",
          "$drupalRootDir/themes/custom/*/*.info.yml",
        ])
        ->run();

      if (!$result->wasSuccessful()) {
        // @todo Error message.
        return 1;
      }

      $buildDir = $data['buildDir'];
      $data['customExtensionDirs'] = [];
      /** @var \Sweetchuck\Robo\Git\ListFilesItem $file */
      foreach ($result['files'] as $file) {
        $data['customExtensionDirs'][] = Path::join($buildDir, Path::getDirectory($file->fileName));
      }

      return 0;
    };
  }

  /**
   * @return \Closure|\Robo\Contract\TaskInterface
   */
  protected function getTaskResolveRelativePackagePaths() {
    return function (RoboStateData $data): int {
      $logger = $this->getLogger();
      $logContext = [
        'taskName' => 'ResolveRelativePackagePaths',
      ];

      $composerInfo = ComposerInfo::create($data['buildDir']);
      $json = $composerInfo->getJson();
      if (empty($json['repositories'])) {
        $logger->debug('{taskName} - empty repositories', $logContext);

        return 0;
      }

      $changed = FALSE;
      $relative = Path::makeRelative($this->srcDir, $data['buildDir']);
      foreach ($json['repositories'] as $repoId => $repo) {
        if (($repo['type'] ?? '') !== 'path') {
          continue;
        }

        $newUrl = $relative . '/' . $repo['url'];

        $logContext['oldUrl'] = $repo['url'];
        $logContext['newUrl'] = $newUrl;
        $logger->debug('{taskName} - {oldUrl} => {newUrl}', $logContext);

        $repo['url'] = $newUrl;
        $repo['options']['symlink'] = FALSE;

        $json['repositories'][$repoId] = $repo;
        $changed = TRUE;
      }

      if ($changed) {
        $this->fs->dumpFile(
          $composerInfo->getJsonFileName(),
          json_encode($json, $this->jsonEncodeFlags)
        );

        $composerInfo->invalidate();
      }

      return 0;
    };
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
   *
   * @return \Closure|\Robo\Contract\TaskInterface
   *
   * @todo Probably a symlink would be much easier.
   */
  protected function getTaskMoveDocroot() {
    return function (RoboStateData $data): int {
      $logger = $this->getLogger();
      $logContext = [
        'taskName' => 'MoveDrupalRootDir',
        'oldDrupalRootDir' => $data['oldDrupalRootDir'],
        'newDrupalRootDir' => $data['newDrupalRootDir'],
      ];

      if ($data['oldDrupalRootDir'] === $data['newDrupalRootDir']) {
        $logger->debug(
          '{taskName} - old and new DrupalRootDir is the same. <info>{oldDrupalRootDir}</info>',
          $logContext
        );

        return 0;
      }

      $logger->debug(
        '{taskName} - from <info>{oldDrupalRootDir}</info> to <info>{newDrupalRootDir}</info>',
        $logContext
      );

      $this->fs->rename(
        Path::join($data['buildDir'], $data['oldDrupalRootDir']),
        Path::join($data['buildDir'], $data['newDrupalRootDir'])
      );

      $drushYmlFileName = Path::join($data['buildDir'], 'drush', 'drush.yml');
      if ($this->fs->exists($drushYmlFileName)) {
        $pattern = "'\${drush.vendor-dir}/../%s'";
        // @todo Figure out a better way to preserve the comments.
        $drushYmlContent = strtr(
          file_get_contents($drushYmlFileName),
          [
            sprintf($pattern, $data['oldDrupalRootDir']) => sprintf($pattern, $data['newDrupalRootDir']),
          ]
        );

        $this->fs->dumpFile($drushYmlFileName, $drushYmlContent);
      }

      $composerInfo = ComposerInfo::create($data['buildDir']);
      $json = $composerInfo->getJson();
      $installerPaths = $json['extra']['installer-paths'] ?? [];
      $json['extra']['installer-paths'] = [];
      $pattern = '@^' . preg_quote($data['oldDrupalRootDir'] . '/', '@') . '@u';

      foreach ($installerPaths as $oldPath => $conditions) {
        $newPath = preg_replace(
          $pattern,
          $data['newDrupalRootDir'] . '/',
          $oldPath
        );

        $json['extra']['installer-paths'][$newPath] = $conditions;
      }

      $this->fs->dumpFile(
        $composerInfo->getJsonFileName(),
        json_encode($json, $this->jsonEncodeFlags)
      );

      $composerInfo->invalidate();

      return 0;
    };
  }

  /**
   * @return \Closure|\Robo\Contract\TaskInterface
   */
  protected function getTaskComposerUpdate() {
    return $this
      ->taskComposerUpdate()
      ->noDev()
      ->noInteraction()
      ->option('no-progress')
      ->option('lock')
      ->deferTaskConfiguration('dir', 'buildDir');
  }

  /**
   * @return \Closure|\Robo\Contract\TaskInterface
   */
  protected function getTaskGitIgnoreEntries() {
    return function (RoboStateData $data): int {
      $w = 0;
      $drupalRootDir = $data['newDrupalRootDir'];
      $data['.gitignore'] = [
        "/$drupalRootDir/sites/*/files/" => ++$w,
        '/sites/*/backup/' => ++$w,
        '/sites/*/php_storage/' => ++$w,
        '/sites/*/private/' => ++$w,
        '/sites/*/hash_salt.txt' => ++$w,
      ];

      return 0;
    };
  }

  /**
   * @return \Closure|\Robo\Contract\TaskInterface
   */
  protected function getTaskGitIgnoreDump() {
    return function (RoboStateData $data): int {
      asort($data['.gitignore']);
      $content = implode("\n", array_keys($data['.gitignore'])) . "\n";
      $fileName = Path::join($data['buildDir'], '.gitignore');
      try {
        $this->fs->dumpFile($fileName, $content);
      }
      catch (IOException $e) {
        return 1;
      }

      return 0;
    };
  }

}
