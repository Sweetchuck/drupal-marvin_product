<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\marvin\ComposerInfo;
use Drush\Commands\marvin\CommandsBase;

class SettingsPhpCommands extends CommandsBase {

  /**
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $yaml;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ?ComposerInfo $composerInfo = NULL,
    ?SerializationInterface $yaml = NULL
  ) {
    parent::__construct($composerInfo);

    $this->yaml = $yaml ?: new Yaml();
  }

  /**
   * @validate marvin:settings-php:add-entry
   */
  public function validateSettingsPhpAddEntry(CommandData $commandData) {
    if (mb_strlen($commandData->input()->getArgument('entry')) > 0
      || is_resource($this->getStdInputFileHandler())
    ) {
      return;
    }

    throw new \Exception('Missing entry', 1);
  }

  /**
   * @command marvin:settings-php:add-entry
   *
   * @bootstrap site
   */
  public function settingsPhpAddEntry(
    string $entry = '',
    array $options = [
      'dst' => 'settings.local.php',
    ]
  ) {
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

    return $entry !== NULL ? $entry : stream_get_contents($this->getStdInputFileHandler());
  }

  /**
   * @return resource|null
   */
  protected function getStdInputFileHandler() {
    if (ftell(STDIN) === 0) {
      return STDIN;
    }

    return NULL;
  }

}
