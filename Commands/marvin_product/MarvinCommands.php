<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Drupal\marvin\Utils as MarvinUtils;
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

  /**
   * @hook validate @marvinOptionCommaSeparatedList
   */
  public function onHookValidateMarvinOptionCommaSeparatedList(CommandData $commandData): ?CommandError {
    $annotationKey = 'marvinOptionCommaSeparatedList';
    $annotationData = $commandData->annotationData();
    if (!$annotationData->has($annotationKey)) {
      return NULL;
    }

    $commandErrors = [];
    $optionNames = $this->parseMultiValueAnnotation($annotationKey, $annotationData->get($annotationKey));
    foreach ($optionNames as $optionName) {
      $commandErrors[] = $this->onHookValidateMarvinOptionCommaSeparatedListSingle($commandData, $optionName);
    }

    return MarvinUtils::aggregateCommandErrors($commandErrors);
  }

  protected function onHookValidateMarvinOptionCommaSeparatedListSingle(CommandData $commandData, string $optionName): ?CommandError {
    $items = $commandData->input()->getOption($optionName);

    $result = [];
    foreach ($items as $itemList) {
      foreach (MarvinUtils::explodeCommaSeparatedList($itemList) as $item) {
        $result[] = $item;
      }
    }

    $commandData->input()->setOption($optionName, $result);

    return NULL;
  }

  /**
   * @hook validate @marvinOptionArrayRequired
   */
  public function onHookValidateMarvinOptionArrayRequired(CommandData $commandData): ?CommandError {
    $annotationKey = 'marvinOptionArrayRequired';
    $annotationData = $commandData->annotationData();
    if (!$annotationData->has($annotationKey)) {
      return NULL;
    }

    $optionNames = $this->parseMultiValueAnnotation($annotationKey, $annotationData->get($annotationKey));
    foreach ($optionNames as $optionName) {
      $value = $commandData->input()->getOption($optionName);
      if (!count($value)) {
        return new CommandError("The --$optionName option is required.", 1);
      }
    }

    return NULL;
  }

  protected function parseMultiValueAnnotation(string $name, string $value): array {
    return MarvinUtils::explodeCommaSeparatedList($value);
  }

}
