<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Unit\Robo\Task;

use Drupal\Tests\marvin_product\Unit\TaskTestBase;
use org\bovigo\vfs\vfsStream;

/**
 * @group marvin_product
 *
 * @covers \Drupal\marvin_product\Robo\Task\GitHooksDeployTask<extended>
 */
class GitHooksDeployTaskTest extends TaskTestBase {

  public function casesRunSuccess(): array {
    return [
      'basic' => [
        [
          'exitCode' => 0,
          'stdOutput' => '',
          'stdError' => " [Marvin - Deploy Git hooks] Deploy Git hooks from vfs://testRunSuccess/src/gitHooks\n",
          'files' => [
            "dst/.git/hooks/_common.php" => 'a',
            "dst/.git/hooks/pre-commit" => 'b',
          ],
        ],
        [
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
        ],
        'dst',
      ],
    ];
  }

  /**
   * @dataProvider casesRunSuccess
   */
  public function testRunSuccess(array $expected, array $vfsStructure, string $projectRootDir) {
    $vfs = vfsStream::setup(__FUNCTION__, NULL, $vfsStructure);
    $rootDir = $vfs->url();

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

    /** @var \Drupal\Tests\marvin_product\Helper\DummyOutput $stdOutput */
    $stdOutput = $this->container->get('output');

    if (array_key_exists('stdOutput', $expected)) {
      static::assertSame(
        $expected['stdOutput'],
        $stdOutput->output,
        'stdOutput',
      );
    }

    if (array_key_exists('stdError', $expected)) {
      static::assertSame(
        $expected['stdError'],
        $stdOutput->getErrorOutput()->output,
        'stdError',
      );
    }

    if (array_key_exists('files', $expected)) {
      foreach ($expected['files'] as $fileName => $fileContent) {
        static::assertFileExists("$rootDir/$fileName");
        static::assertStringEqualsFile("$rootDir/$fileName", $fileContent);
      }
    }
  }

}
