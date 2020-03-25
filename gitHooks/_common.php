<?php

/**
 * @file
 * Git hook callback handler for managed extensions.
 */

use Drupal\marvin_product\GitHookHandler;

call_user_func(function () {
  $originalGitHookArgs = $GLOBALS['argv'][1];
  $gitHook = basename($originalGitHookArgs);

  // Use a text file with a line break separated list of applicable hooks (_githooks-config.txt)
  $gitHooksConfigFilePath = '_githooks-config.txt';
  if (file_exists(__DIR__ . "/$gitHooksConfigFilePath")) {
    $gitHooksConfigFile = file_get_contents(__DIR__ . "/$gitHooksConfigFilePath");
    if (strpos($gitHooksConfigFile, $gitHook) === false) {
      exit();
    }
  }

  $composerExecutable = '';
  $gitHookHandlerPath = '';
  $drushConfigPaths = [];

  if (!class_exists(GitHookHandler::class)) {
    require_once $gitHookHandlerPath;
  }

  $gitHookHandler = new GitHookHandler();
  register_shutdown_function([$gitHookHandler, 'writeFooter']);

  $context = $gitHookHandler
    ->init(
      array($originalGitHookArgs),
      $composerExecutable,
      $drushConfigPaths
    )
    ->writeHeader()
    ->doIt();

  if ($context) {
    $_SERVER['argv'] = $GLOBALS['argv'] = $context['cliArgs'];
    $_SERVER['argc'] = $GLOBALS['argc'] = count($context['cliArgs']);

    require $context['pathToDrush'];
  }
});
