<?php

declare(strict_types = 1);

namespace Drupal\marvin_product;

class GitHookHandler {

  /**
   * @var string
   */
  protected $composerExecutable = '';

  /**
   * @var string[]
   */
  protected $drushConfigPaths = [];

  /**
   * @var string
   */
  protected $gitHook = '';

  /**
   * @var string
   */
  protected $drushCommand = '';

  /**
   * @var string
   */
  protected $binDir = '';

  /**
   * @var resource
   */
  protected $stdOutput;

  /**
   * @var resource
   */
  protected $stdError;

  /**
   * @var array
   */
  protected $originalGitHookArgs = [];

  /**
   * @param resource $stdOutput
   * @param resource $stdError
   */
  public function __construct($stdOutput = NULL, $stdError = NULL) {
    $this->stdOutput = $stdOutput ?? STDOUT;
    $this->stdError = $stdError ?? STDERR;
  }

  /**
   * @return $this
   */
  public function init(array $originalGitHookArgs, string $composerExecutable, array $drushConfigPaths) {
    $this->originalGitHookArgs = $originalGitHookArgs;
    $this->composerExecutable = $composerExecutable;
    $this->drushConfigPaths = $drushConfigPaths;

    return $this
      ->initGitHook()
      ->initDrushCommand()
      ->initBinDir();
  }

  /**
   * @return $this
   */
  protected function initGitHook() {
    $this->gitHook = basename($this->originalGitHookArgs[0]);

    return $this;
  }

  /**
   * @return $this
   */
  protected function initDrushCommand() {
    $this->drushCommand = "marvin:git-hook:{$this->gitHook}";

    return $this;
  }

  /**
   * @return $this
   */
  protected function initBinDir() {
    $output = exec(sprintf(
      '%s config bin-dir 2>/dev/null',
      escapeshellcmd($this->composerExecutable)
    ));

    $this->binDir = $this->getLastLine($output) ?: 'vendor/bin';

    return $this;
  }

  public function doIt(): ?array {
    if (!$this->isDrushCommandExists()) {
      $this->logError("There is no corresponding 'drush marvin:git-hook:{$this->gitHook}' command.");

      return NULL;
    }

    return $this->getContext();
  }

  /**
   * @return $this
   */
  public function writeHeader() {
    $this->logError("BEGIN {$this->gitHook}");

    return $this;
  }

  /**
   * @return $this
   */
  public function writeFooter() {
    $this->logError("END   {$this->gitHook}");

    return $this;
  }

  protected function getDrushCommandPrefix(): string {
    $cmdPattern = '%s';
    $cmdArgs = [
      escapeshellcmd("{$this->binDir}/drush")
    ];

    $cmdPattern .= str_repeat(' --config=%s', count($this->drushConfigPaths));
    foreach ($this->drushConfigPaths as $drushCmdOptionConfig) {
      $cmdArgs[] = escapeshellarg($drushCmdOptionConfig);
    }

    return vsprintf($cmdPattern, $cmdArgs);
  }

  protected function isDrushCommandExists(): bool {
    $cmdPattern = '%s help %s 2>&1';
    $cmdArgs = [
      $this->getDrushCommandPrefix(),
      escapeshellarg($this->drushCommand),
    ];

    $output = NULL;
    $exitCode = NULL;
    exec(vsprintf($cmdPattern, $cmdArgs), $output, $exitCode);

    return $exitCode === 0;
  }

  protected function getContext(): array {
    $gitHookArgsWithoutExecutable = $this->originalGitHookArgs;
    array_shift($gitHookArgsWithoutExecutable);

    $context = [
      'cliArgs' => [
        "{$this->binDir}/drush",
        '--define=options.progress-delay=9999',
        "--define=marvin.gitHook={$this->gitHook}",
      ],
      'pathToDrush' => "{$this->binDir}/drush",
    ];

    foreach ($this->drushConfigPaths as $drushConfigPath) {
      $context['cliArgs'][] = "--config={$drushConfigPath}";
    }

    $context['cliArgs'][] = $this->drushCommand;

    $context['cliArgs'] = array_merge($context['cliArgs'], $gitHookArgsWithoutExecutable);

    return $context;
  }

  /**
   * @return $this
   */
  protected function logOutput(string $message) {
    $this->log($this->stdOutput, $message);

    return $this;
  }

  /**
   * @return $this
   */
  protected function logError(string $message) {
    $this->log($this->stdError, $message);

    return $this;
  }

  /**
   * @param resource $output
   * @param string $message
   *
   * @return $this
   */
  protected function log($output, string $message) {
    fwrite($output, $message . PHP_EOL);

    return $this;
  }

  protected function getLastLine(string $string): string {
    $lines = preg_split('/[\n\r]+/', trim($string));

    return (string) end($lines);
  }

}

