<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\marvin\ComposerInfo;
use Drush\Boot\DrupalBootLevels;
use Drush\Attributes as CLI;
use Drush\Commands\marvin\CommandsBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @todo Delete these commands.
 */
class EnvironmentCommands extends CommandsBase implements ContainerInjectionInterface {

  protected ?ModuleInstallerInterface $moduleInstaller;

  protected ?ModuleHandlerInterface $moduleHandler;

  protected ?ModuleExtensionList $moduleLister;

  /**
   * @var string[]
   */
  protected array $modulesToUninstall = [];

  /**
   * @var string[]
   */
  protected array $modulesToInstall = [];

  /**
   * @var string[]
   */
  protected array $installedModules = [];

  /**
   * @var string[]
   */
  protected array $uninstalledModules = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      NULL,
      $container->get('module_installer'),
      $container->get('module_handler'),
      $container->get('extension.list.module')
    );
  }

  public function __construct(
    ?ComposerInfo $composerInfo = NULL,
    ?ModuleInstallerInterface $moduleInstaller = NULL,
    ?ModuleHandlerInterface $moduleHandler = NULL,
    ?ModuleExtensionList $moduleLister = NULL
  ) {
    $this->moduleInstaller = $moduleInstaller;
    $this->moduleHandler = $moduleHandler;
    $this->moduleLister = $moduleLister;

    parent::__construct($composerInfo);
  }

  /**
   * Installs and uninstalls modules based on the configuration in drush.yml.
   *
   * Used configurations:
   * - marvin.environment;
   * - marvin.environments.*.modules;
   *
   * @todo Create a native Robo task.
   */
  #[CLI\Command(name: 'marvin:toggle-modules')]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  #[CLI\Argument(
    name: 'environment',
    description: 'Environment identifier',
  )]
  public function cmdMarvinToggleModulesExecute(string $environment = '') {
    $environment = $environment ?: $this->getEnvironment();

    $this
      ->toggleModulesInitTodo($environment)
      ->toggleModulesInitModules()
      ->toggleModulesUninstall()
      ->toggleModulesInstall();
  }

  protected function toggleModulesInitTodo(string $environment): static {
    $environmentModules = $this->getEnvironmentModules($environment);
    $this->modulesToUninstall = array_keys($environmentModules, FALSE, TRUE);
    $this->modulesToInstall = array_keys($environmentModules, TRUE, TRUE);

    return $this;
  }

  protected function toggleModulesInitModules(): static {
    $moduleLister = $this->getModuleLister();
    $moduleLister->reset();
    $this->installedModules = array_keys($moduleLister->getAllInstalledInfo());
    $this->uninstalledModules = array_diff(array_keys($moduleLister->getList()), $this->installedModules);

    return $this;
  }

  protected function toggleModulesUninstall(): static {
    $logger = $this->getLogger();

    $alreadyUninstalledModules = array_intersect($this->uninstalledModules, $this->modulesToUninstall);
    if ($alreadyUninstalledModules) {
      $logger->debug(
        "Already uninstalled modules: {moduleNames}",
        [
          'moduleNames' => implode(', ', $alreadyUninstalledModules),
        ]
      );
    }

    $missingModules = array_diff(
      $this->modulesToUninstall,
      $this->installedModules,
      $this->uninstalledModules
    );

    if ($missingModules) {
      $logger->debug(
        "Following modules are marked to uninstall, but they are missing: {moduleNames}",
        [
          'moduleNames' => implode(', ', $missingModules),
        ]
      );
    }

    $modulesToUninstall = array_intersect($this->installedModules, $this->modulesToUninstall);
    $modulesToUninstall = array_diff($modulesToUninstall, $missingModules);
    if (!$modulesToUninstall) {
      $logger->debug('There is no module to uninstall');

      return $this;
    }

    $logger->debug(
      "Modules to uninstall: {moduleNames}",
      [
        'moduleNames' => implode(', ', $modulesToUninstall),
      ]
    );

    $moduleInstaller = $this->getModuleInstaller();
    $errorMessages = $moduleInstaller->validateUninstall($modulesToUninstall);
    if ($errorMessages) {
      throw new \Exception(
        implode(PHP_EOL, $errorMessages),
        1
      );
    }

    $moduleInstaller->uninstall($modulesToUninstall);

    return $this;
  }

  protected function toggleModulesInstall(): static {
    $logger = $this->getLogger();

    $alreadyInstalledModules = array_intersect($this->installedModules, $this->modulesToInstall);
    if ($alreadyInstalledModules) {
      $logger->debug(
        'Already installed modules: {moduleNames}',
        [
          'moduleNames' => implode(', ', $alreadyInstalledModules),
        ]
      );
    }

    $modulesToInstall = array_diff($this->modulesToInstall, $this->installedModules);
    if (!$modulesToInstall) {
      $logger->debug('there is no module to install');

      return $this;
    }

    $logger->debug(
      "Modules to install: {moduleNames}",
      [
        'moduleNames' => implode(', ', $modulesToInstall),
      ]
    );

    $this->getModuleInstaller()->install($modulesToInstall);

    return $this;
  }

  protected function getEnvironmentModules(string $environment): array {
    // @todo Something wrong with the config management.
    // Config overrides do not take effects.
    $config = $this->getConfig()->export();

    return $config['marvin']['environments'][$environment]['modules'] ?? [];
  }

  protected function getModuleInstaller(): ModuleInstallerInterface {
    if (!$this->moduleInstaller) {
      $this->moduleInstaller = $this->service('module_installer');
    }

    return $this->moduleInstaller;
  }

  protected function getModuleHandler(): ModuleHandlerInterface {
    if (!$this->moduleHandler) {
      $this->moduleHandler = $this->service('module_handler');
    }

    return $this->moduleHandler;
  }

  protected function getModuleLister(): ModuleExtensionList {
    if (!$this->moduleLister) {
      $this->moduleLister = $this->service('extension.list.module');
    }

    return $this->moduleLister;
  }

  protected function service(string $serviceName) {
    if ($this->getContainer()->has($serviceName)) {
      return $this->getContainer()->get($serviceName);
    }

    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    return \Drupal::hasService($serviceName) ? \Drupal::service($serviceName) : NULL;
  }

}
