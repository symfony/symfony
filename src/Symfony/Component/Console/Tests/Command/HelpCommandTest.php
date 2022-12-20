<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;

class HelpCommandTest extends TestCase
{
    public function testExecuteForCommandAlias()
    {
        $command = new HelpCommand();
        $command->setApplication(new Application());
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command_name' => 'li'], ['decorated' => false]);
        self::assertStringContainsString('list [options] [--] [<namespace>]', $commandTester->getDisplay(), '->execute() returns a text help for the given command alias');
        self::assertStringContainsString('format=FORMAT', $commandTester->getDisplay(), '->execute() returns a text help for the given command alias');
        self::assertStringContainsString('raw', $commandTester->getDisplay(), '->execute() returns a text help for the given command alias');
    }

    public function testExecuteForCommand()
    {
        $command = new HelpCommand();
        $commandTester = new CommandTester($command);
        $command->setCommand(new ListCommand());
        $commandTester->execute([], ['decorated' => false]);
        self::assertStringContainsString('list [options] [--] [<namespace>]', $commandTester->getDisplay(), '->execute() returns a text help for the given command');
        self::assertStringContainsString('format=FORMAT', $commandTester->getDisplay(), '->execute() returns a text help for the given command');
        self::assertStringContainsString('raw', $commandTester->getDisplay(), '->execute() returns a text help for the given command');
    }

    public function testExecuteForCommandWithXmlOption()
    {
        $command = new HelpCommand();
        $commandTester = new CommandTester($command);
        $command->setCommand(new ListCommand());
        $commandTester->execute(['--format' => 'xml']);
        self::assertStringContainsString('<command', $commandTester->getDisplay(), '->execute() returns an XML help text if --xml is passed');
    }

    public function testExecuteForApplicationCommand()
    {
        $application = new Application();
        $commandTester = new CommandTester($application->get('help'));
        $commandTester->execute(['command_name' => 'list']);
        self::assertStringContainsString('list [options] [--] [<namespace>]', $commandTester->getDisplay(), '->execute() returns a text help for the given command');
        self::assertStringContainsString('format=FORMAT', $commandTester->getDisplay(), '->execute() returns a text help for the given command');
        self::assertStringContainsString('raw', $commandTester->getDisplay(), '->execute() returns a text help for the given command');
    }

    public function testExecuteForApplicationCommandWithXmlOption()
    {
        $application = new Application();
        $commandTester = new CommandTester($application->get('help'));
        $commandTester->execute(['command_name' => 'list', '--format' => 'xml']);
        self::assertStringContainsString('list [--raw] [--format FORMAT] [--short] [--] [&lt;namespace&gt;]', $commandTester->getDisplay(), '->execute() returns a text help for the given command');
        self::assertStringContainsString('<command', $commandTester->getDisplay(), '->execute() returns an XML help text if --format=xml is passed');
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        require_once realpath(__DIR__.'/../Fixtures/FooCommand.php');
        $application = new Application();
        $application->add(new \FooCommand());
        $tester = new CommandCompletionTester($application->get('help'));
        $suggestions = $tester->complete($input, 2);
        self::assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        yield 'option --format' => [
            ['--format', ''],
            ['txt', 'xml', 'json', 'md'],
        ];

        yield 'nothing' => [
            [''],
            ['completion', 'help', 'list', 'foo:bar'],
        ];

        yield 'command_name' => [
            ['f'],
            ['completion', 'help', 'list', 'foo:bar'],
        ];
    }
}
