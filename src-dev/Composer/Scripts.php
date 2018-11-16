<?php

declare(strict_types = 1);

namespace Drupal\marvin_product\Dev\Composer;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Sweetchuck\GitHooks\Composer\Scripts as GitHooks;

class Scripts {

  /**
   * Current event.
   *
   * @var \Composer\Script\Event
   */
  protected static $event;

  /**
   * CLI process callback.
   *
   * @var \Closure
   */
  protected static $processCallbackWrapper;

  /**
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected static $fs;

  /**
   * Composer event callback.
   */
  public static function postInstallCmd(Event $event): int {
    static::init($event);
    static::gitHooksDeploy();
    static::phpcsConfigSet();

    return 0;
  }

  /**
   * Composer event callback.
   */
  public static function postUpdateCmd(Event $event): int {
    static::init($event);
    static::gitHooksDeploy();
    static::phpcsConfigSet();

    return 0;
  }

  protected static function init(Event $event) {
    static::$event = $event;
    static::$fs = new Filesystem();

    if (!static::$processCallbackWrapper) {
      static::$processCallbackWrapper = function (string $type, string $buffer) {
        static::processCallback($type, $buffer);
      };
    }
  }

  protected static function gitHooksDeploy(): void {
    if (!static::$event->isDevMode()) {
      return;
    }

    GitHooks::deploy(static::$event);
  }

  protected static function phpcsConfigSet(): void {
    if (!static::$event->isDevMode()) {
      return;
    }

    /** @var \Composer\Config $config */
    $config = static::$event->getComposer()->getConfig();

    $phpcsExecutable = $config->get('bin-dir') . '/phpcs';
    $rulesDir = $config->get('vendor-dir') . '/drupal/coder/coder_sniffer';
    if (!static::$fs->exists($phpcsExecutable) || !static::$fs->exists($rulesDir)) {
      return;
    }

    $cmdPattern = '%s --config-set installed_paths %s';
    $cmdArgs = [
      escapeshellcmd($phpcsExecutable),
      escapeshellcmd($rulesDir),
    ];

    static::processRun('.', vsprintf($cmdPattern, $cmdArgs));
  }

  protected static function processRun(string $workingDirectory, string $command): Process {
    static::$event->getIO()->write("Run '$command' in '$workingDirectory'");
    $process = new Process($command, NULL, NULL, NULL, 0);
    $process->setWorkingDirectory($workingDirectory);
    $process->run(static::$processCallbackWrapper);

    return $process;
  }

  protected static function processCallback(string $type, string $buffer): void {
    $type === Process::OUT ?
      static::$event->getIO()->write($buffer, FALSE)
      : static::$event->getIO()->writeError($buffer, FALSE);
  }

}

