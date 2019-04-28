<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\marvin_product\EnvConfig\DrupalConfigConverter;
use Drupal\marvin_product\EnvConfig\EnvConfigHandler;
use Drush\Commands\marvin\CommandsBase;
use Drush\Internal\Config\Yaml\Yaml;

class EnvConfigCommands extends CommandsBase {

  /**
   * @command marvin:env-config:settings-php
   *
   * @marvinOptionCommaSeparatedList sites
   *
   * @usage drush marvin:env-config:settings-php --sites=default --target=staging < my-env-config.yml
   * @usage drush marvin:env-config:settings-php --sites=default --target=staging "data://text/plain;base64,${myEnvConfigYamlBase64Encoded}"
   *
   * @code
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
   * @endcode
   *
   * --sites=all --target=dev
   * @code
   * $a['b']['c'] = 'my-value';
   * @endcode
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

  protected function fileGetContents(string $fileName): string {
    $content = file_get_contents($fileName);
    if ($content === FALSE) {
      throw new \RuntimeException("file '$fileName' could not be read", 1);
    }

    return $content;
  }

}
