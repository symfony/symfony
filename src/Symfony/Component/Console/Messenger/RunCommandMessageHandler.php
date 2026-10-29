<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Messenger;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RunCommandFailedException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RunCommandMessageHandler
{
    /**
     * @param string $console The path to the console entry point, required to run isolated commands
     */
    public function __construct(
        private readonly Application $application,
        private readonly string $console = '',
    ) {
    }

    public function __invoke(RunCommandMessage $message): RunCommandContext
    {
        if ($message->isolated) {
            return $this->runIsolated($message);
        }

        $input = new StringInput($message->input);
        $output = new BufferedOutput();

        $originalCatchExceptions = $this->application->areExceptionsCaught();
        $this->application->setCatchExceptions($message->catchExceptions);

        try {
            $exitCode = $this->application->run($input, $output);
        } catch (UnrecoverableExceptionInterface|RecoverableExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RunCommandFailedException($e, new RunCommandContext($message, Command::FAILURE, $output->fetch()));
        } finally {
            $this->application->setCatchExceptions($originalCatchExceptions);
        }

        if ($message->throwOnFailure && Command::SUCCESS !== $exitCode) {
            throw new RunCommandFailedException(\sprintf('Command "%s" exited with code "%s".', $message->input, $exitCode), new RunCommandContext($message, $exitCode, $output->fetch()));
        }

        return new RunCommandContext($message, $exitCode, $output->fetch());
    }

    /**
     * Runs the command in a separate PHP process, so that it gets a fresh memory
     * limit and cannot terminate the process handling the message.
     */
    private function runIsolated(RunCommandMessage $message): RunCommandContext
    {
        if (!class_exists(Process::class)) {
            throw new LogicException(\sprintf('Running command "%s" in a separate process requires the Process component. Try running "composer require symfony/process".', $message->input));
        }

        if (!$this->console) {
            throw new LogicException(\sprintf('Running command "%s" in a separate process requires the console entry point to be configured.', $message->input));
        }

        if (!is_file($this->console)) {
            throw new LogicException(\sprintf('Running command "%s" in a separate process requires the console entry point to exist, but "%s" does not.', $message->input, $this->console));
        }

        $finder = new PhpExecutableFinder();

        if (false === $php = $finder->find(false)) {
            throw new RuntimeException('Unable to find the PHP executable.');
        }

        $process = new Process([$php, ...$finder->findArguments(), $this->console, ...(new StringInput($message->input))->getRawTokens()], timeout: $message->timeout);

        try {
            $exitCode = $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new RunCommandFailedException($e, new RunCommandContext($message, Command::FAILURE, $this->fetchOutput($process)));
        }

        $output = $this->fetchOutput($process);

        if ($message->throwOnFailure && Command::SUCCESS !== $exitCode) {
            throw new RunCommandFailedException(\sprintf('Command "%s" exited with code "%s".', $message->input, $exitCode), new RunCommandContext($message, $exitCode, $output));
        }

        return new RunCommandContext($message, $exitCode, $output);
    }

    private function fetchOutput(Process $process): string
    {
        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();

        if ('' !== $output && '' !== $errorOutput) {
            $output = rtrim($output, "\n")."\n";
        }

        return $output.$errorOutput;
    }
}
