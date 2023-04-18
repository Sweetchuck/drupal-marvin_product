<?php

declare(strict_types = 1);

namespace Drupal\marvin_product\Robo\Task;

use Consolidation\AnnotatedCommand\Output\OutputAwareInterface;
use Drupal\marvin\Robo\Task\BaseTask;
use Drupal\marvin_product\Utils as MarvinProductUtils;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Robo\Common\IO;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @deprecated
 */
class GitHooksDeployTask extends BaseTask implements
  ContainerAwareInterface,
  OutputAwareInterface {

  use ContainerAwareTrait;
  use IO;

  protected Filesystem $fs;

  protected string $hookFilesSourceDir = '';

  public function __construct() {
    $this->taskName = 'Marvin - Deploy Git hooks';
  }

  public function getHookFilesSourceDir(): string {
    return $this->hookFilesSourceDir;
  }

  public function setHookFilesSourceDir(string $value): static {
    $this->hookFilesSourceDir = $value;

    return $this;
  }

  protected string $commonTemplateFileName = '';

  public function getCommonTemplateFileName(): string {
    return $this->commonTemplateFileName;
  }

  public function setCommonTemplateFileName(string $value): static {
    $this->commonTemplateFileName = $value;

    return $this;
  }

  protected string $projectRootDir = '';

  public function getProjectRootDir(): string {
    return $this->projectRootDir;
  }

  /**
   * Absolute path to the project root dir.
   */
  public function setProjectRootDir(string $value): static {
    $this->projectRootDir = $value;

    return $this;
  }

  protected string $composerExecutable = 'composer';

  public function getComposerExecutable(): string {
    return $this->composerExecutable;
  }

  public function setComposerExecutable(string $value): static {
    $this->composerExecutable = $value;

    return $this;
  }

  /**
   * @var string[]
   */
  protected array $drushConfigPaths = [];

  public function getDrushConfigPaths(): array {
    return $this->drushConfigPaths;
  }

  public function setDrushConfigPaths(array $drushConfigPaths): static {
    $this->drushConfigPaths = $drushConfigPaths;

    return $this;
  }

  public function setOptions(array $options): static {
    parent::setOptions($options);

    if (array_key_exists('hookFilesSourceDir', $options)) {
      $this->setHookFilesSourceDir($options['hookFilesSourceDir']);
    }

    if (array_key_exists('commonTemplateFileName', $options)) {
      $this->setCommonTemplateFileName($options['commonTemplateFileName']);
    }

    if (array_key_exists('projectRootDir', $options)) {
      $this->setProjectRootDir($options['projectRootDir']);
    }

    if (array_key_exists('composerExecutable', $options)) {
      $this->setComposerExecutable($options['composerExecutable']);
    }

    if (array_key_exists('drushConfigPaths', $options)) {
      $this->setDrushConfigPaths($options['drushConfigPaths']);
    }

    return $this;
  }

  protected function runPrepare(): static {
    parent::runPrepare();

    $this->fs = new Filesystem();

    return $this;
  }

  protected function runHeader(): static {
    $this->printTaskInfo(
      'Deploy Git hooks from <info>{hookFilesSourceDir}</info>',
      [
        'hookFilesSourceDir' => $this->getHookFilesSourceDir(),
      ]
    );

    return $this;
  }

  protected function runAction(): static {
    $context = [
      'getHookFilesSourceDir' => $this->getHookFilesSourceDir(),
      'commonTemplateFileName' => $this->getCommonTemplateFileName(),
      'projectRootDir' => $this->getProjectRootDir(),
    ];

    // @todo Create a runValidate() method.
    if (!$this->fs->exists($context['getHookFilesSourceDir'])) {
      $this->printTaskError("The hookFilesSourceDir '<info>{hookFilesSourceDir}</info>' directory does not exists.", $context);

      // @todo Set an error.
      return $this;
    }

    if (!$this->fs->exists($context['commonTemplateFileName'])) {
      $this->printTaskError("The commonTemplateFileName '<info>{commonTemplateFileName}</info>' file does not exists.", $context);

      // @todo Set an error.
      return $this;
    }

    if (!$this->fs->exists("{$context['projectRootDir']}/.git")) {
      $this->printTaskError("The projectRootDir '<info>{projectRootDir}</info>' is not a Git repository.", $context);

      // @todo Set an error.
      return $this;
    }

    return $this
      ->runActionPrepareDestinationDir()
      ->runActionCopyHookFiles()
      ->runActionCopyCommonFile();
  }

  protected function runActionPrepareDestinationDir(): static {
    $destinationDir = $this->getDestinationDir();

    if (is_link($destinationDir)) {
      $this->fs->remove($destinationDir);
    }

    if (!$this->fs->exists($destinationDir)) {
      $this->fs->mkdir($destinationDir, 0777 - umask());

      return $this;
    }

    $directDescendants = (new Finder())
      ->in($destinationDir)
      ->depth('== 0')
      ->ignoreDotFiles(TRUE);

    $this->fs->remove($directDescendants);

    return $this;
  }

  protected function runActionCopyHookFiles(): static {
    /** @var \Symfony\Component\Finder\SplFileInfo[] $hookFiles */
    $hookFiles = $this->getHookFiles($this->getHookFilesSourceDir());
    $destinationDir = $this->getDestinationDir();

    foreach ($hookFiles as $hookFile) {
      $this->fs->copy($hookFile->getPathname(), Path::join($destinationDir, $hookFile->getFilename()));
    }

    return $this;
  }

  protected function runActionCopyCommonFile(): static {
    $this->fs->dumpFile(
      Path::join($this->getDestinationDir(), '_common.php'),
      $this->replaceTemplateVariables(file_get_contents($this->getCommonTemplateFileName()))
    );

    return $this;
  }

  protected function replaceTemplateVariables(string $content): string {
    $marvinProductDir = MarvinProductUtils::marvinProductDir();
    $projectRootDir = $this->getProjectRootDir();
    if (MarvinProductUtils::urlsHaveSameScheme($marvinProductDir, $projectRootDir)) {
      $marvinProductDir = Path::makeRelative($marvinProductDir, $projectRootDir);
    }

    $variables = [
      '$composerExecutable' => [
        'from' => "'';\n",
        'to' => var_export($this->getComposerExecutable() ?: 'composer', TRUE) . ";\n",
      ],
      '$gitHookHandlerPath' => [
        'from' => "'';\n",
        'to' => var_export("{$marvinProductDir}/src/GitHookHandler.php", TRUE) . ";\n",
      ],
      '$drushConfigPaths' => [
        'from' => "[];\n",
        'to' => ['['],
      ],
    ];

    $paths = $this->getDrushConfigPaths();
    foreach ($paths as $path) {
      $variables['$drushConfigPaths']['to'][] = sprintf('    %s,', var_export($path, TRUE));
    }

    if ($paths) {
      $variables['$drushConfigPaths']['to'][] = '  ]';
    }
    else {
      $variables['$drushConfigPaths']['to'][0] .= ']';
    }

    $variables['$drushConfigPaths']['to'] = implode("\n", $variables['$drushConfigPaths']['to']) . ";\n";

    $pattern = "  %s = %s";
    $replacePairs = [];
    foreach ($variables as $varName => $pairs) {
      $from = sprintf($pattern, $varName, $pairs['from']);
      $replacePairs[$from] = sprintf($pattern, $varName, $pairs['to']);
    }

    return strtr($content, $replacePairs);
  }

  protected function getDestinationDir(): string {
    // @todo Support for ".git" file.
    return Path::join($this->getProjectRootDir(), '.git', 'hooks');
  }

  protected function getHookFiles(string $dir): Finder {
    return (new Finder())
      ->in($dir)
      ->notName('/^_/')
      ->depth('== 0')
      ->files();
  }

}
