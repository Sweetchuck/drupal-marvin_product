<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\CommandsBase;
use Robo\Collection\CollectionBuilder;
use Sweetchuck\Robo\Git\GitTaskLoader;
use Webmozart\PathUtil\Path;

class BehatCommands extends CommandsBase {

  use GitTaskLoader;

  /**
   * @command marvin:test:behat
   */
  public function testBehat(): CollectionBuilder {
    return $this
      ->collectionBuilder()
      ->addTask($this->getTaskBehatConfigFinder())
      ->addTask($this->getTaskBehatRunAll());
  }

  protected function getTaskBehatConfigFinder() {
    $paths = [
      'behat.yml' => TRUE,
      '*/behat.yml' => TRUE,
    ];

    return $this
      ->taskGitListFiles()
      ->setPaths($paths);
  }

  /**
   * @return \Robo\Collection\CollectionBuilder
   */
  protected function getTaskBehatRunAll(): CollectionBuilder {
    return $this->taskForEach()
      ->deferTaskConfiguration('setIterable', 'files')
      ->withBuilder(
        function (CollectionBuilder $builder, string $behatYmlFileName) {
          $builder->addCode($this->getTaskBehatRunSingle($behatYmlFileName));
        }
      );
  }

  protected function getTaskBehatRunSingle(string $behatYmlFileName): \Closure {
    return function () use ($behatYmlFileName) {
      $behatDir = Path::getDirectory($behatYmlFileName);
      $behatExecutable = $this->getBehatExecutable($behatDir);

      $cmdPattern = ['%s'];
      $cmdArgs = [
        escapeshellcmd($behatExecutable),
      ];

      $result = $this
        ->taskExec(vsprintf(implode(' ', $cmdPattern), $cmdArgs))
        ->dir($behatDir)
        ->run();

      if (!$result->wasSuccessful()) {
        $logger = $this->getLogger();
        $logger->error($result->getMessage());

        return max($result->getExitCode(), 1);
      }

      return 0;
    };
  }

  protected function getBehatExecutable(string $behatDir): string {
    $projectRootDir = $this->getProjectRootDir();
    $composerInfo = $this->getComposerInfo();

    return Path::join(
      Path::makeRelative($projectRootDir, Path::join($projectRootDir, $behatDir)),
      $composerInfo['config']['bin-dir'],
      'behat'
    );
  }

}
