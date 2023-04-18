<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\marvin\ComposerInfo;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Robo\Collection\CollectionBuilder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ArtifactBuildVanillaCommands extends ArtifactBuildProductCommandsBase {

  public function __construct(
    ?ComposerInfo $composerInfo = NULL,
    ?Filesystem $fs = NULL,
  ) {
    $this->artifactType = 'vanilla';

    parent::__construct($composerInfo, $fs);
  }

  #[CLI\Hook(
    type: HookManager::ON_EVENT,
    target: 'marvin:artifact:types',
  )]
  public function onEventMarvinArtifactTypes(string $projectType): array {
    if (!$this->isApplicable($projectType)) {
      var_dump("\$projectType = $projectType");
      return [];
    }

    return [
      $this->artifactType => [
        'label' => 'Vanilla',
        'description' => 'Not customized',
      ],
    ];
  }

  /**
   * Builds the Vanilla release artifact.
   *
   * @todo Validate "version-bump" option.
   */
  #[CLI\Command(name: 'marvin:artifact:build:vanilla')]
  #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
  #[CLI\Option(
    name: 'version-bump',
    description: 'Exact version number or semantic version number fragment name.',
  )]
  public function cmdMarvinArtifactBuildVanillaExecute(
    array $options = [
      'version-bump' => 'minor',
    ],
  ): CollectionBuilder {
    return $this->delegate($this->artifactType);
  }

  #[CLI\Hook(
    type: HookManager::ON_EVENT,
    target: 'marvin:artifact:build:vanilla',
  )]
  public function onEventMarvinArtifactBuildVanilla(InputInterface $input): array {
    $this->srcDir = '.';
    $this->artifactDir = (string) $this->getConfig()->get('marvin.artifactDir');
    $this->versionPartToBump = $input->getOption('version-bump');

    return $this->getBuildSteps();
  }

}
