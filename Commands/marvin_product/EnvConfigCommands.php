<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\Component\Utility\NestedArray;
use Drupal\marvin_product\EnvConfig\DrupalConfigConverter;
use Drupal\marvin_product\EnvConfig\EnvConfigHandler;
use Drupal\marvin_product\EnvConfig\SitesPhpGenerator;
use Drush\Commands\marvin\CommandsBase;
use Drush\Internal\Config\Yaml\Yaml;
use RuntimeException;

class EnvConfigCommands extends CommandsBase {

  /**
   * @hook validate marvin:env-config:settings-php
   */
  public function exportSettingsPhpValidate(CommandData $commandData) {
    $input = $commandData->input();
    if (!$input->getOption('target')) {
      $input->setOption('target', (string) $this->getConfig()->get('marvin.environment'));
    }
  }

  /**
   * Export EnvVars as config override PHP code.
   *
   * ```yaml
   * -
   *   sites:
   *     all: true
   *   key: [a, b ,c]
   *   value:
   *     dev:
   *       type: value
   *       value: my-value
   *     stage:
   *       type: envVarNow
   *       value: MY_ENV_VAR_01
   *     prod:
   *       type: envVarLater
   *       value: MY_ENV_VAR_02
   * ```
   *
   * Run `drush marvin:env-config:settings-php --sites=all --target=dev ...`
   *
   * ```
   * $a['b']['c'] = 'my-value';
   * ```
   *
   * @command marvin:env-config:settings-php
   * @bootstrap none
   * @marvinOptionCommaSeparatedList sites
   *
   * @usage drush marvin:env-config:settings-php --sites=default --target=staging < my-env-config.yml
   *   Read the YAML content from StdInput.
   * @usage drush marvin:env-config:settings-php --sites=default --target=staging "data://text/plain;base64,${myEnvConfigYamlBase64Encoded}"
   *   Read YAML content from file.
   *   https://www.php.net/manual/en/wrappers.data.php
   */
  public function exportSettingsPhp(
    string $fileName = '',
    array $options = [
      'target' => '',
      'sites' => [],
    ]
  ) {
    if ($fileName === '') {
      $fileName = 'php://stdin';
    }

    $envConfig = Yaml::parse($this->fileGetContents($fileName));

    $envConfigHandler = new EnvConfigHandler();
    $envConfigSettingsPhp = (new DrupalConfigConverter())
      ->getKeyValuePairs(
        $envConfigHandler->normalize($envConfig, $options['target']),
        $options['sites']
      );

    return CommandResult::data($envConfigSettingsPhp);
  }

  /**
   * Generates sites.php from host_name:dir_name mapping.
   *
   * ```yaml
   * hosts:
   *   a: b
   * ```
   *
   * @command marvin:env-config:sites-php
   *
   * @usage drush marvin:env-config:sites-php --env-var-name-pattern='FOO_{{ upper }}_BAR' --parents=hosts /path/to/config.yml
   *   Reads host_name:dir_name mapping from /path/to/config.yml
   *   Result: $sites = [getenv('FOO_A_BAR') => 'b'];
   */
  public function sitesPhp(
    string $fileName,
    array $options = [
      'env-var-name-pattern' => '',
      'parents' => [],
    ]
  ) {
    if ($fileName === '') {
      $fileName = 'php://stdin';
    }

    $envConfig = (array) Yaml::parse($this->fileGetContents($fileName));
    $mapping = (array) NestedArray::getValue($envConfig, $options['parents']);

    $sitesPhpContent = (new SitesPhpGenerator())
      ->setEnvVarNamePattern($options['env-var-name-pattern'])
      ->setMapping($mapping)
      ->generate();

    return CommandResult::data($sitesPhpContent);
  }

  protected function fileGetContents(string $fileName): string {
    $content = file_get_contents($fileName);
    if ($content === FALSE) {
      throw new RuntimeException("file '$fileName' could not be read", 1);
    }

    return $content;
  }

}
