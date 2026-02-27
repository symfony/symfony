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
use Symfony\Component\Console\Command\TraceableCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tests\Fixtures\InvokableTestCommand;
use Symfony\Component\Console\Tests\Fixtures\InvokableWithAskCommand;
use Symfony\Component\Console\Tests\Fixtures\LoopExampleCommand;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableCommandTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->application->addCommand(new LoopExampleCommand());
        $this->application->addCommand(new InvokableTestCommand());
    }

    public function testRunIsOverriddenWithoutProfile()
    {
        $command = $this->application->find('app:loop:example');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertLoopOutputCorrectness($output);
    }

    public function testRunIsNotOverriddenWithProfile()
    {
        // Simulate the bug environment by wrapping
        // our command in TraceableCommand, which is what Symfony does
        // when you use the --profile option.
        $command = new LoopExampleCommand();
        $traceableCommand = new TraceableCommand($command, new Stopwatch());

        $this->application->addCommand($traceableCommand);

        $commandTester = new CommandTester($traceableCommand);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertLoopOutputCorrectness($output);
    }

    public function testRunOnInvokableCommand()
    {
        $command = $this->application->find('invokable:test');
        $traceableCommand = new TraceableCommand($command, new Stopwatch());

        $commandTester = new CommandTester($traceableCommand);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testRunOnInvokableCommandWithAskAttribute()
    {
        $this->application->addCommand(new InvokableWithAskCommand());
        $command = $this->application->find('invokable:ask');
        $traceableCommand = new TraceableCommand($command, new Stopwatch());

        $commandTester = new CommandTester($traceableCommand);
        $commandTester->setInputs(['World']);
        $commandTester->execute([], ['interactive' => true]);
        $commandTester->assertCommandIsSuccessful();

        self::assertStringContainsString('What is your name?', $commandTester->getDisplay());
        self::assertStringContainsString('Hello World', $commandTester->getDisplay());
    }

    public function testArgumentsCaptureValueSetDuringInteract()
    {
        $this->application->addCommand(new InvokableWithAskCommand());
        $command = $this->application->find('invokable:ask');
        $traceableCommand = new TraceableCommand($command, new Stopwatch());

        $commandTester = new CommandTester($traceableCommand);
        $commandTester->setInputs(['Robin']);
        $commandTester->execute([], ['interactive' => true]);
        $commandTester->assertCommandIsSuccessful();

        self::assertSame('Robin', $traceableCommand->arguments['name']);
        self::assertTrue($traceableCommand->isInteractive);
        self::assertSame(['name' => 'Robin'], $traceableCommand->interactiveInputs);
    }

    public function assertLoopOutputCorrectness(string $output)
    {
        $completeChar = '\\' !== \DIRECTORY_SEPARATOR ? '▓' : '=';
        self::assertMatchesRegularExpression('~3/3\s+\['.$completeChar.'+]\s+100%~u', $output);
        self::assertStringContainsString('Loop finished.', $output);
        self::assertEquals(3, substr_count($output, 'Hello world'));
    }
}
