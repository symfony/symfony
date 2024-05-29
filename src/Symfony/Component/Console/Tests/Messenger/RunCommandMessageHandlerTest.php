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
use Symfony\Component\Console\Exception\RunCommandFailedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Console\Messenger\RunCommandMessageHandler;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RunCommandMessageHandlerTest extends TestCase
{
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
        $context = $handler(new RunCommandMessage('test:command --throw -v', throwOnFailure: false, catchExceptions: true));

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

    private function createApplicationWithCommand(): Application
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommands([
            new class() extends Command {
                public function configure(): void
                {
                    $this
                        ->setName('test:command')
                        ->addOption('throw')
                        ->addOption('exit', null, InputOption::VALUE_REQUIRED, 0)
                    ;
                }

                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $output->write('some message');

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
