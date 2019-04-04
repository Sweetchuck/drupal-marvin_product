<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\ArtifactCommandsBase;
use Robo\Collection\CollectionBuilder;

class ArtifactCommands extends ArtifactCommandsBase {

  /**
   * @command marvin:artifact:build
   * @bootstrap none
   *
   * @option string $type
   *   For the available values run "marvin:artifact:types"
   * @option string $version-bump
   *   One of the following "major", "minor", "patch", "pre-release" or a
   *   semantic version number e.g: "1.2.3".
   *
   * @todo Validate "type" option.
   */
  public function artifactBuild(
    array $options = [
      'type' => 'vanilla',
      'version-bump' => 'minor',
    ]
  ): CollectionBuilder {
    return $this->delegate('build');
  }

}
