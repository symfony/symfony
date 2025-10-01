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
use Symfony\Component\Console\Tests\Fixtures\LoopExampleCommand;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableCommandTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->application->addCommand(new LoopExampleCommand());
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

    public function assertLoopOutputCorrectness(string $output)
    {
        $completeChar = '\\' !== \DIRECTORY_SEPARATOR ? '▓' : '=';
        self::assertMatchesRegularExpression('~3/3\s+\['.$completeChar.'+]\s+100%~u', $output);
        self::assertStringContainsString('Loop finished.', $output);
        self::assertEquals(3, substr_count($output, 'Hello world'));
    }
}
