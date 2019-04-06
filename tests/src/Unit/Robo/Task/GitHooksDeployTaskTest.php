<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Unit\Robo\Task;

use Drupal\Tests\marvin_product\Unit\TaskTestBase;
use org\bovigo\vfs\vfsStream;

class GitHooksDeployTaskTest extends TaskTestBase {

  public function testRunSuccess() {
    $vfsStructure = [
      'dst' => [
        '.git' => [
          'hooks' => [
            '_common.php' => 'c',
          ],
        ],
      ],
      'src' => [
        'gitHooks' => [
          '_common.php' => 'a',
          'pre-commit' => 'b',
        ],
      ],
    ];

    $vfs = vfsStream::setup(__FUNCTION__, NULL, $vfsStructure);
    $rootDir = $vfs->url();
    $projectRootDir = 'dst';
    $taskName = 'Marvin - Deploy Git hooks';

    $expected = [
      'exitCode' => 0,
      'stdOutput' => '',
      'stdError' => '',
      'logEntries' => [
        [
          'notice',
          'Deploy Git hooks from <info>{hookFilesSourceDir}</info>',
          [
            'hookFilesSourceDir' => "$rootDir/src/gitHooks",
            'name' => $taskName,
          ],
        ],
      ],
      'files' => [
        "$projectRootDir/.git/hooks/_common.php" => 'a',
        "$projectRootDir/.git/hooks/pre-commit" => 'b',
      ],
    ];

    $task = $this
      ->taskBuilder
      ->taskMarvinGitHooksDeploy()
      ->setContainer($this->container)
      ->setHookFilesSourceDir("$rootDir/src/gitHooks")
      ->setCommonTemplateFileName("$rootDir/src/gitHooks/_common.php")
      ->setProjectRootDir("$rootDir/$projectRootDir")
      ->setDrushConfigPaths([
        'a.yml',
        'b.yml',
      ]);

    $result = $task->run();

    if (array_key_exists('exitCode', $expected)) {
      static::assertSame($expected['exitCode'], $result->getExitCode());
    }

    /** @var \Drupal\Tests\marvin\Helper\DummyOutput $stdOutput */
    $stdOutput = $this->container->get('output');

    if (array_key_exists('stdOutput', $expected)) {
      static::assertSame($expected['stdOutput'], $stdOutput->output);
    }

    if (array_key_exists('stdError', $expected)) {
      static::assertSame($expected['stdError'], $stdOutput->getErrorOutput()->output);
    }

    if (array_key_exists('logEntries', $expected)) {
      static::assertRoboTaskLogEntries($expected['logEntries'], $task->logger()->cleanLogs());
    }

    if (array_key_exists('files', $expected)) {
      foreach ($expected['files'] as $fileName => $fileContent) {
        static::assertFileExists("$rootDir/$fileName");
        static::assertStringEqualsFile("$rootDir/$fileName", $fileContent);
      }
    }
  }

}
