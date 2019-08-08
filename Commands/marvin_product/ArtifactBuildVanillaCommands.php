<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Robo\Collection\CollectionBuilder;
use Symfony\Component\Console\Input\InputInterface;

class ArtifactBuildVanillaCommands extends ArtifactBuildProductCommandsBase {

  /**
   * {@inheritdoc}
   */
  protected $artifactType = 'vanilla';

  /**
   * @hook on-event marvin:artifact:types
   */
  public function onEventMarvinArtifactTypes(string $projectType): array {
    if (!$this->isApplicable($projectType)) {
      return [];
    }

    return [
      $this->artifactType => [
        'label' => dt('Vanilla'),
        'description' => dt('Not customized'),
      ],
    ];
  }

  /**
   * @command marvin:artifact:build:vanilla
   * @bootstrap none
   *
   * @todo Validate "version-bump" option.
   * @todo Rename this method.
   */
  public function artifactBuildVanilla(
    array $options = [
      'version-bump' => 'minor',
    ]
  ): CollectionBuilder {
    return $this->delegate($this->artifactType);
  }

  /**
   * @hook on-event marvin:artifact:build
   */
  public function onEventMarvinArtifactBuild(InputInterface $input): array {
    $this->srcDir = '.';
    $this->artifactDir = (string) $this->getConfig()->get('marvin.artifactDir');
    $this->versionPartToBump = $input->getOption('version-bump');

    return $this->getBuildSteps();
  }

  /**
   * @hook on-event marvin:artifact:build:vanilla
   */
  public function onEventMarvinArtifactBuildVanilla(InputInterface $input): array {
    $this->srcDir = '.';
    $this->artifactDir = (string) $this->getConfig()->get('marvin.artifactDir');
    $this->versionPartToBump = $input->getOption('version-bump');

    return $this->getBuildSteps();
  }

}
