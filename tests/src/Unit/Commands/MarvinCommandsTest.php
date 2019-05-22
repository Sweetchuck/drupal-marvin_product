<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_product\Unit\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Drush\Commands\marvin_product\MarvinCommands;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @group marvin_product
 *
 * @covers \Drush\Commands\marvin_product\MarvinCommands<extended>
 */
class MarvinCommandsTest extends TestCase {

  public function casesOnHookValidateMarvinOptionCommaSeparatedList(): array {
    $id = new InputDefinition([
      new InputOption(
        'foo',
        NULL,
        InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
        'My desc',
        []
      ),
    ]);

    $adYes = new AnnotationData(['marvinOptionCommaSeparatedList' => ['foo']]);
    $adNo = new AnnotationData([]);

    $input = new ArrayInput(['--foo' => ['a,b', 'c,d']], $id);

    $output = new BufferedOutput();

    return [
      'without annotation' => [
        ['a,b', 'c,d'],
        new CommandData($adNo, $input, $output),
      ],
      'success' => [
        ['a', 'b', 'c', 'd'],
        new CommandData($adYes, $input, $output),
      ],
    ];
  }

  /**
   * @dataProvider casesOnHookValidateMarvinOptionCommaSeparatedList
   */
  public function testOnHookValidateMarvinOptionCommaSeparatedList(array $expected, CommandData $commandData): void {
    $subject = new MarvinCommands();

    static::assertNull($subject->onHookValidateMarvinOptionCommaSeparatedList($commandData));
    static::assertSame($expected, $commandData->input()->getOption('foo'));
  }

  public function casesOnHookValidateMarvinOptionArrayRequired(): array {
    $id = new InputDefinition([
      new InputOption(
        'foo',
        NULL,
        InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
        'My desc',
        []
      ),
    ]);

    $adYes = new AnnotationData(['marvinOptionArrayRequired' => ['foo']]);
    $adNo = new AnnotationData([]);

    $inputSuccess = new ArrayInput(['--foo' => ['a']], $id);
    $inputFail = new ArrayInput([], $id);

    $output = new BufferedOutput();

    return [
      'without annotation' => [
        NULL,
        new CommandData($adNo, $inputSuccess, $output),
      ],
      'success' => [
        NULL,
        new CommandData($adYes, $inputSuccess, $output),
      ],
      'fail' => [
        new CommandError('The --foo option is required.', 1),
        new CommandData($adYes, $inputFail, $output),
      ],
    ];
  }

  /**
   * @dataProvider casesOnHookValidateMarvinOptionArrayRequired
   */
  public function testOnHookValidateMarvinOptionArrayRequired(?CommandError $expected, CommandData $commandData): void {
    $subject = new MarvinCommands();

    static::assertEquals($expected, $subject->onHookValidateMarvinOptionArrayRequired($commandData));
  }

}
