<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\CommandsBase;
use Sweetchuck\Robo\Git\GitTaskLoader;
use Symfony\Component\Console\Input\InputInterface;

class MarvinCommands extends CommandsBase {

  use GitTaskLoader;

  /**
   * @hook on-event marvin:git-hook:post-checkout
   */
  public function onEventMarvinGitHookPostCheckout(InputInterface $input): array {
    return [
      'marvin:git-list-changed-files' => [
        'weight' => -900,
        'task' => $this
          ->taskGitListChangedFiles()
          ->setWorkingDirectory($this->getProjectRootDir())
          ->setFromRevName($input->getArgument('refPrevious'))
          ->setToRevName($input->getArgument('refHead'))
          ->setAssetNamePrefix('changed.'),
      ],
    ];
  }

}
