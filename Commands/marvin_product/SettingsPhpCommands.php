<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\marvin\ComposerInfo;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\marvin\CommandsBase;

class SettingsPhpCommands extends CommandsBase {

  protected SerializationInterface $yaml;

  public function __construct(
    ?ComposerInfo $composerInfo = NULL,
    ?SerializationInterface $yaml = NULL
  ) {
    parent::__construct($composerInfo);

    $this->yaml = $yaml ?: new Yaml();
  }

  #[CLI\Hook(
    type: HookManager::ARGUMENT_VALIDATOR,
    target: 'marvin:settings-php:add-entry',
  )]
  public function cmdMarvinSettingsPhpAddEntryValidate(CommandData $commandData) {
    if (mb_strlen($commandData->input()->getArgument('entry')) > 0
      || is_resource($this->getStdInputFileHandler())
    ) {
      return;
    }

    throw new \Exception('Missing entry', 1);
  }

  /**
   * Not finished.
   *
   * @phpstan-param array<string, mixed> $options
   *   Not used.
   */
  #[CLI\Command(name: 'marvin:settings-php:add-entry')]
  #[CLI\Bootstrap(level: DrupalBootLevels::SITE)]
  #[CLI\Argument(
    name: 'entry',
    description: 'YAML encoded entry definition.',
  )]
  public function cmdMarvinSettingsPhpAddEntryExecute(
    string $entry = '',
    array $options = [
      'dst' => 'settings.local.php',
    ]
  ): void {
    $entry = $this->getEntry();
    $phpFragment = $this->parseEntry($entry);

    $this->output()->writeln($phpFragment);
  }

  protected function parseEntry(string $entry): string {
    $parts = $this->yaml::decode($entry);

    return $this->parseEntryKey($parts['key']) . ' = ' . $this->parseEntryValue($parts['value']) . ';';
  }

  protected function parseEntryKey(array $keys): string {
    $variable = '$' . array_shift($keys);
    if ($keys) {
      foreach ($keys as $key) {
        $variable .= '[' . var_export($key, TRUE) . ']';
      }
    }

    return $variable;
  }

  protected function parseEntryValue($value): string {
    return var_export($value, TRUE);
  }

  protected function getEntry(): string {
    $entry = $this->input()->getArgument('entry');

    return $entry ?? stream_get_contents($this->getStdInputFileHandler());
  }

  /**
   * @return null|resource
   */
  protected function getStdInputFileHandler() {
    if (ftell(STDIN) === 0) {
      return STDIN;
    }

    return NULL;
  }

}
