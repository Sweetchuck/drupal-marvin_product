<?php

/**
 * @file
 * Git hook callback handler for managed extensions.
 */

use Drupal\marvin_product\GitHookHandler;

call_user_func(function () {
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
      $GLOBALS['argv'],
      $composerExecutable,
      $drushConfigPaths
    )
    ->writeHeader()
    ->doIt();

  if ($context) {
    $_SERVER['argv'] = $GLOBALS['argv'] = $context['cliArgs'];
    $_SERVER['argc'] = $GLOBALS['argc'] = count($context['cliArgs']);

    require $context['pathToDrushPhp'];
  }
});
