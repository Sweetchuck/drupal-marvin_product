<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\marvin\Robo\GitCommitMsgValidatorTaskLoader;
use Drush\Commands\marvin\CommandsBase;
use Robo\Collection\CollectionBuilder;
use Sweetchuck\Utils\Filter\ArrayFilterEnabled;
use Symfony\Component\Console\Input\InputInterface;
use Webmozart\PathUtil\Path;

class GitHookCommitMsgCommands extends CommandsBase {

  use GitCommitMsgValidatorTaskLoader;

  /**
   * @hook on-event marvin:git-hook:commit-msg:info
   */
  public function onEventMarvinGitHookCommitMsgInfo(): array {
    $rules = array_filter($this->getRules(), new ArrayFilterEnabled());
    if (!$rules) {
      return [];
    }

    return [
      'marvin.commit-msg-validator' => [
        'description' => 'Validate commit message with regular expressions',
      ],
    ];
  }

  /**
   * @hook on-event marvin:git-hook:commit-msg
   */
  public function onEventMarvinGitHookCommitMsg(InputInterface $input): array {
    $commitMsgFileName = $input->getArgument('commitMsgFileName');
    if (!file_exists($commitMsgFileName)) {
      $commitMsgFileName = Path::join($this->getProjectRootDir(), $commitMsgFileName);
    }

    return [
      'marvin.commit-msg-validator' => [
        'weight' => 0,
        'task' => $this->getTaskGitCommitMsgValidator($commitMsgFileName, $this->getRules()),
      ],
    ];
  }

  /**
   * @return \Robo\Collection\CollectionBuilder|\Drupal\marvin\Robo\Task\GitCommitMsgValidatorTask
   */
  protected function getTaskGitCommitMsgValidator(string $commitMsgFileName, array $rules): CollectionBuilder {
    return $this
      ->taskMarvinGitCommitMsgValidator()
      ->setFileName($commitMsgFileName)
      ->setRules($rules);
  }

  protected function getRules(): array {
    return $this
      ->getConfig()
      ->get('marvin.git-hook.commit-msg.settings.rules', []);
  }

}
