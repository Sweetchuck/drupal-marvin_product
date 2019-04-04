<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_product;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\marvin\ComposerInfo;
use Drush\Commands\marvin\CommandsBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EnvironmentCommands extends CommandsBase implements ContainerInjectionInterface {

  /**
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleLister;

  /**
   * @var string[]
   */
  protected $modulesToUninstall = [];

  /**
   * @var string[]
   */
  protected $modulesToInstall = [];

  /**
   * @var string[]
   */
  protected $installedModules = [];

  /**
   * @var string[]
   */
  protected $uninstalledModules = [];

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

  /**
   * {@inheritdoc}
   */
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
   * - command.marvin.settings.environment;
   * - command.marvin.settings.environments.*.modules;
   *
   * @command marvin:toggle-modules
   * @bootstrap full
   *
   * @todo Create a native Robo task.
   */
  public function toggleModules() {
    $this
      ->toggleModulesInitTodo()
      ->toggleModulesInitModules()
      ->toggleModulesUninstall()
      ->toggleModulesInstall();
  }

  /**
   * @return $this
   */
  protected function toggleModulesInitTodo() {
    $environmentModules = $this->getEnvironmentModules();
    $this->modulesToUninstall = array_keys($environmentModules, FALSE, TRUE);
    $this->modulesToInstall = array_keys($environmentModules, TRUE, TRUE);

    return $this;
  }

  /**
   * @return $this
   */
  public function toggleModulesInitModules() {
    $moduleLister = $this->getModuleLister();
    $moduleLister->reset();
    $this->installedModules = array_keys($moduleLister->getAllInstalledInfo());
    $this->uninstalledModules = array_diff(array_keys($moduleLister->getList()), $this->installedModules);

    return $this;
  }

  /**
   * @return $this
   */
  protected function toggleModulesUninstall() {
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

    $modulesToUninstall = array_intersect($this->installedModules, $this->modulesToUninstall);
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

  /**
   * @return $this
   */
  protected function toggleModulesInstall() {
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
        'moduleNames' => implode(', ', $modulesToInstall) ,
      ]
    );

    $this->getModuleInstaller()->install($modulesToInstall);

    return $this;
  }

  protected function getEnvironmentModules(): array {
    $env = $this->getEnvironment();
    $configName = "command.marvin.settings.environments.{$env}.modules";

    return $this->getConfig()->get($configName, []);
  }

  protected function getModuleInstaller(): ModuleInstallerInterface {
    $serviceName = 'module_installer';
    if (!$this->moduleInstaller && $this->getContainer()->has($serviceName)) {
      $this->moduleInstaller = $this->getContainer()->get($serviceName);
    }

    if (!$this->moduleInstaller) {
      $this->moduleInstaller = \Drupal::service($serviceName);
    }

    return $this->moduleInstaller;
  }

  protected function getModuleHandler(): ModuleHandlerInterface {
    $serviceName = 'module_handler';
    if (!$this->moduleHandler && $this->getContainer()->has($serviceName)) {
      $this->moduleHandler = $this->getContainer()->get($serviceName);
    }

    if (!$this->moduleHandler) {
      $this->moduleHandler = \Drupal::service($serviceName);
    }

    return $this->moduleHandler;
  }

  protected function getModuleLister(): ModuleExtensionList {
    $serviceName = 'extension.list.module';
    if (!$this->moduleLister && $this->getContainer()->has($serviceName)) {
      $this->moduleLister = $this->getContainer()->get($serviceName);
    }

    if (!$this->moduleLister) {
      $this->moduleLister = \Drupal::service($serviceName);
    }

    return $this->moduleLister;
  }

}
