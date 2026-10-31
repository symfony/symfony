<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RunCommandFailedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Console\Messenger\RunCommandMessageHandler;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RunCommandMessageHandlerTest extends TestCase
{
    private const CONSOLE = __DIR__.'/../Fixtures/application_isolated_command.php';
    private const CONSUMER = __DIR__.'/../Fixtures/run_isolated_command_handler.php';

    public function testExecutesCommand()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand());
        $context = $handler(new RunCommandMessage('test:command'));

        $this->assertSame(0, $context->exitCode);
        $this->assertStringContainsString('some message', $context->output);
    }

    public function testExecutesCommandThatThrowsException()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand());

        try {
            $handler(new RunCommandMessage('test:command --throw'));
        } catch (RunCommandFailedException $e) {
            $this->assertSame(1, $e->context->exitCode);
            $this->assertStringContainsString('some message', $e->context->output);
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            $this->assertSame('exception message', $e->getMessage());

            return;
        }

        $this->fail('Exception not thrown.');
    }

    public function testExecutesCommandThatCatchesThrownException()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand());
        $context = $handler(new RunCommandMessage('test:command --throw -v', false, true));

        $this->assertSame(1, $context->exitCode);
        $this->assertStringContainsString('[RuntimeException]', $context->output);
        $this->assertStringContainsString('exception message', $context->output);
    }

    public function testThrowOnNonSuccess()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand());

        try {
            $handler(new RunCommandMessage('test:command --exit=1'));
        } catch (RunCommandFailedException $e) {
            $this->assertSame(1, $e->context->exitCode);
            $this->assertStringContainsString('some message', $e->context->output);
            $this->assertSame('Command "test:command --exit=1" exited with code "1".', $e->getMessage());
            $this->assertNull($e->getPrevious());

            return;
        }

        $this->fail('Exception not thrown.');
    }

    public function testExecutesCommandThatThrownUnrecoverableException()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand());

        try {
            $handler(new RunCommandMessage('test:command --throw-unrecoverable'));
        } catch (UnrecoverableExceptionInterface $e) {
            $this->assertSame('Unrecoverable exception message', $e->getMessage());
            $this->assertNull($e->getPrevious());

            return;
        }

        $this->fail('Exception not thrown.');
    }

    public function testExecutesCommandThatThrownRecoverableException()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand());

        try {
            $handler(new RunCommandMessage('test:command --throw-recoverable'));
        } catch (RecoverableExceptionInterface $e) {
            $this->assertSame('Recoverable exception message', $e->getMessage());
            $this->assertNull($e->getPrevious());

            return;
        }

        $this->fail('Exception not thrown.');
    }

    public function testCatchExceptionsIsRestored()
    {
        $application = $this->createApplicationWithCommand();
        $application->setCatchExceptions(true);

        $handler = new RunCommandMessageHandler($application);
        $handler(new RunCommandMessage('test:command'));

        $this->assertTrue($application->areExceptionsCaught());
    }

    public function testCatchExceptionsIsRestoredWhenCommandFails()
    {
        $application = $this->createApplicationWithCommand();
        $application->setCatchExceptions(true);

        $handler = new RunCommandMessageHandler($application);

        try {
            $handler(new RunCommandMessage('test:command --throw'));
        } catch (RunCommandFailedException) {
        }

        $this->assertTrue($application->areExceptionsCaught());
    }

    public function testExecutesIsolatedCommand()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand(), self::CONSOLE);
        $context = $handler(new RunCommandMessage('test:command --stderr', isolated: true));

        $this->assertSame(0, $context->exitCode);
        $this->assertStringContainsString('some message', $context->output);
        $this->assertStringContainsString('some error', $context->output);
    }

    public function testExecutesIsolatedCommandInItsOwnProcess()
    {
        $application = $this->createApplicationWithCommand();
        $handler = new RunCommandMessageHandler($application, self::CONSOLE);

        // "--sleep" is only defined on the command of the fixture console, so the
        // command cannot have run on the application the handler holds
        $context = $handler(new RunCommandMessage('test:command --sleep=0', isolated: true));

        $this->assertSame(0, $context->exitCode);
        $this->assertStringContainsString('some message', $context->output);
    }

    public function testThrowOnNonSuccessForIsolatedCommand()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand(), self::CONSOLE);

        try {
            $handler(new RunCommandMessage('test:command --exit=2', isolated: true));
        } catch (RunCommandFailedException $e) {
            $this->assertSame(2, $e->context->exitCode);
            $this->assertStringContainsString('some message', $e->context->output);
            $this->assertSame('Command "test:command --exit=2" exited with code "2".', $e->getMessage());

            return;
        }

        $this->fail('Exception not thrown.');
    }

    public function testIsolatedCommandIsKilledOnTimeout()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand(), self::CONSOLE);

        try {
            $handler(new RunCommandMessage('test:command --sleep=10', isolated: true, timeout: 0.5));
        } catch (RunCommandFailedException $e) {
            $this->assertSame(Command::FAILURE, $e->context->exitCode);
            $this->assertInstanceOf(ProcessTimedOutException::class, $e->getPrevious());

            return;
        }

        $this->fail('Exception not thrown.');
    }

    public function testIsolatedCommandRequiresTheConsoleEntryPoint()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Running command "test:command" in a separate process requires the console entry point to be configured.');

        $handler(new RunCommandMessage('test:command', isolated: true));
    }

    public function testIsolatedCommandRequiresAnExistingConsoleEntryPoint()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand(), '/nonexistent/bin/console');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Running command "test:command" in a separate process requires the console entry point to exist, but "/nonexistent/bin/console" does not.');

        $handler(new RunCommandMessage('test:command', isolated: true));
    }

    public function testIsolatedCommandKeepsItsErrorOutputOnItsOwnLine()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand(), self::CONSOLE);
        $context = $handler(new RunCommandMessage('test:command --stderr', isolated: true));

        $this->assertSame("some message\nsome error", $context->output);
    }

    public function testIsolatedCommandThatThrowsUnrecoverableExceptionBecomesRecoverable()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand(), self::CONSOLE);

        try {
            $handler(new RunCommandMessage('test:command --throw-unrecoverable', true, false, true));
        } catch (RunCommandFailedException $e) {
            // the exception of the command cannot cross the process boundary, so a
            // failing isolated command is retried like any other failure
            $this->assertNotInstanceOf(UnrecoverableExceptionInterface::class, $e);
            $this->assertSame(1, $e->context->exitCode);
            $this->assertStringContainsString('some message', $e->context->output);

            return;
        }

        $this->fail('Exception not thrown.');
    }

    public function testIsolatedCommandsDoNotOverlap()
    {
        $handler = new RunCommandMessageHandler($this->createApplicationWithCommand(), self::CONSOLE);

        $start = microtime(true);
        $handler(new RunCommandMessage('test:command --sleep=1', true, false, true));
        $handler(new RunCommandMessage('test:command --sleep=1', true, false, true));

        $this->assertGreaterThanOrEqual(2, microtime(true) - $start);
    }

    public function testIsolatedCommandKilledOnTimeoutDoesNotRunToCompletion()
    {
        $markerFile = tempnam(sys_get_temp_dir(), 'sf_isolated_');

        try {
            $handler = new RunCommandMessageHandler($this->createApplicationWithCommand(), self::CONSOLE);

            try {
                $handler(new RunCommandMessage('test:command --sleep=2 --marker-file='.$markerFile, true, false, true, 0.5));
                $this->fail('Exception not thrown.');
            } catch (RunCommandFailedException) {
            }

            // a child that survived would finish sleeping and append its marker within this window
            usleep(2500000);

            $this->assertSame('started', file_get_contents($markerFile));
        } finally {
            @unlink($markerFile);
        }
    }

    public function testIsolatedCommandRunsToCompletionWhenTheConsumerIsKilled()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows kills the whole process tree.');
        }

        $markerFile = tempnam(sys_get_temp_dir(), 'sf_isolated_');

        try {
            $finder = new PhpExecutableFinder();
            $consumer = new Process([$finder->find(false), ...$finder->findArguments(), self::CONSUMER, self::CONSOLE, 'test:command --sleep=2 --marker-file='.$markerFile]);
            $consumer->start();

            $this->assertSame('started', self::waitForMarker($markerFile, 'started'));

            $consumer->stop(0, 9); // the consumer dies abruptly, without running destructors

            $this->assertSame('started done', self::waitForMarker($markerFile, 'done'));
        } finally {
            @unlink($markerFile);
        }
    }

    public function testTimeoutMustBePositive()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The timeout of command "test:command" must be a positive number of seconds, or null to let the command run to completion.');

        new RunCommandMessage('test:command', isolated: true, timeout: 0);
    }

    public function testTimeoutRequiresAnIsolatedCommand()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A timeout can only be set on an isolated command, as command "test:command" cannot be interrupted while it runs in the current process.');

        new RunCommandMessage('test:command', timeout: 30);
    }

    private static function waitForMarker(string $markerFile, string $marker): string
    {
        $deadline = microtime(true) + 10;

        do {
            if (str_contains($contents = (string) @file_get_contents($markerFile), $marker)) {
                return $contents;
            }

            usleep(50000);
        } while (microtime(true) < $deadline);

        return $contents;
    }

    private function createApplicationWithCommand(): Application
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommands([
            new class extends Command {
                public function configure(): void
                {
                    $this
                        ->setName('test:command')
                        ->addOption('throw')
                        ->addOption('throw-unrecoverable')
                        ->addOption('throw-recoverable')
                        ->addOption('exit', null, InputOption::VALUE_REQUIRED, 0)
                    ;
                }

                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $output->write('some message');

                    if ($input->getOption('throw-unrecoverable')) {
                        throw new UnrecoverableMessageHandlingException('Unrecoverable exception message');
                    }

                    if ($input->getOption('throw-recoverable')) {
                        throw new RecoverableMessageHandlingException('Recoverable exception message');
                    }

                    if ($input->getOption('throw')) {
                        throw new \RuntimeException('exception message');
                    }

                    return (int) $input->getOption('exit');
                }
            },
        ]);

        return $application;
    }
}
