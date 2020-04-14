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
    $taskName = 'Marvin - Deploy Git hooks';

    $logDeployFrom = [
      'notice',
      'Deploy Git hooks from <info>{hookFilesSourceDir}</info>',
      [
        'hookFilesSourceDir' => "vfs://testRunSuccess/src/gitHooks",
        'name' => $taskName,
      ],
    ];

    $logNoSubscriber = [
      'notice',
      'Git hook script deployment is skipped because there is no subscriber',
      [
        'name' => $taskName,
      ],
    ];

    $logDeploy = [
      'notice',
      'Deploy scripts for the following Git hooks: {gitHooks}',
      [
        'gitHooks' => 'pre-commit',
        'name' => 'Marvin - Deploy Git hooks',
      ],
    ];

    return [
      'no hooks to deploy; already existing files should not be touched' => [
        [
          'exitCode' => 0,
          'stdOutput' => '',
          'stdError' => '',
          'logEntries' => [
            $logDeployFrom,
            $logNoSubscriber,
          ],
          'files' => [
            "dst/.git/hooks/_common.php" => 'old:common',
            "dst/.git/hooks/pre-commit" => 'old:pre-commit',
          ],
        ],
        [
          'hookFilesSourceDir' => 'src/gitHooks',
          'commonTemplateFileName' => 'src/gitHooks/_common.php',
          'projectRootDir' => 'dst',
          'drushConfigPaths' => [
            'a.yml',
            'b.yml',
          ],
        ],
        [
          'dst' => [
            '.git' => [
              'hooks' => [
                '_common.php' => 'old:common',
                'pre-commit' => 'old:pre-commit',
              ],
            ],
          ],
          'src' => [
            'gitHooks' => [
              '_common.php' => 'new:common',
              'pre-commit' => 'new:pre-commit',
            ],
          ],
        ],
      ],
      'one hook to deploy; already existing files should be removed' => [
        [
          'exitCode' => 0,
          'stdOutput' => '',
          'stdError' => '',
          'logEntries' => [
            $logDeployFrom,
            $logDeploy,
          ],
          'files' => [
            "dst/.git/hooks/_common.php" => 'new:common',
            "dst/.git/hooks/pre-commit" => 'new:pre-commit',
            "dst/.git/hooks/pre-push" => NULL,
            "dst/.git/hooks/pre-rebase" => NULL,
          ],
        ],
        [
          'hookFilesSourceDir' => 'src/gitHooks',
          'commonTemplateFileName' => 'src/gitHooks/_common.php',
          'projectRootDir' => 'dst',
          'gitHooksToDeploy' => [
            'pre-commit' => TRUE,
          ],
          'drushConfigPaths' => [
            'a.yml',
            'b.yml',
          ],
        ],
        [
          'dst' => [
            '.git' => [
              'hooks' => [
                '_common.php' => 'old:common',
                'pre-commit' => 'old:pre-commit',
                'pre-push' => 'old:pre-push',
              ],
            ],
          ],
          'src' => [
            'gitHooks' => [
              '_common.php' => 'new:common',
              'pre-commit' => 'new:pre-commit',
              'pre-push' => 'new:pre-push',
              'pre-rebase' => 'new:pre-rebase',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * @dataProvider casesRunSuccess
   */
  public function testRunSuccess(array $expected, array $options, array $vfsStructure) {
    $vfs = vfsStream::setup(__FUNCTION__, NULL, $vfsStructure);
    $rootDir = $vfs->url();

    $pathOptions = [
      'hookFilesSourceDir',
      'commonTemplateFileName',
      'projectRootDir',
    ];
    foreach ($pathOptions as $pathOption) {
      if (array_key_exists($pathOption, $options)) {
        $options[$pathOption] = $rootDir . '/' . $options[$pathOption];
      }
    }

    $task = $this
      ->taskBuilder
      ->taskMarvinGitHooksDeploy()
      ->setContainer($this->container)
      ->setOptions($options);

    $result = $task->run();

    if (array_key_exists('exitCode', $expected)) {
      static::assertSame($expected['exitCode'], $result->getExitCode());
    }

    /** @var \Drupal\Tests\marvin_product\Helper\DummyOutput $stdOutput */
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
        if ($fileContent === NULL) {
          static::assertFileNotExists("$rootDir/$fileName");

          continue;
        }

        static::assertFileExists("$rootDir/$fileName");
        static::assertStringEqualsFile(
          "$rootDir/$fileName",
          $fileContent,
          "file content $fileName"
        );
      }
    }
  }

}
