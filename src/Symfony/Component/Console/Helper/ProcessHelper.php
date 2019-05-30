<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * The ProcessHelper class provides helpers to run external processes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class ProcessHelper extends Helper
{
    /**
     * Runs an external process.
     *
     * @param OutputInterface $output    An OutputInterface instance
     * @param array|Process   $cmd       An instance of Process or an array of the command and arguments
     * @param string|null     $error     An error message that must be displayed if something went wrong
     * @param callable|null   $callback  A PHP callback to run whenever there is some
     *                                   output available on STDOUT or STDERR
     * @param int             $verbosity The threshold for verbosity
     *
     * @return Process The process that ran
     */
    public function run(OutputInterface $output, $cmd, $error = null, callable $callback = null, $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE)
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        $formatter = $this->getHelperSet()->get('debug_formatter');

        if ($cmd instanceof Process) {
            $cmd = [$cmd];
        }

        if (!\is_array($cmd)) {
            throw new \TypeError(sprintf('The "command" argument of "%s()" must be an array or a "%s" instance, "%s" given.', __METHOD__, Process::class, \is_object($cmd) ? \get_class($cmd) : \gettype($cmd)));
        }

        if (\is_string($cmd[0] ?? null)) {
            $process = new Process($cmd);
            $cmd = [];
        } elseif (($cmd[0] ?? null) instanceof Process) {
            $process = $cmd[0];
            unset($cmd[0]);
        } else {
            throw new \InvalidArgumentException(sprintf('Invalid command provided to "%s()": the command should be an array whose first element is either the path to the binary to run or a "Process" object.', __METHOD__));
        }

        if ($verbosity <= $output->getVerbosity()) {
            $output->write($formatter->start(spl_object_hash($process), $this->escapeString($process->getCommandLine())));
        }

        if ($output->isDebug()) {
            $callback = $this->wrapCallback($output, $process, $callback);
        }

        $process->run($callback, $cmd);

        if ($verbosity <= $output->getVerbosity()) {
            $message = $process->isSuccessful() ? 'Command ran successfully' : sprintf('%s Command did not run successfully', $process->getExitCode());
            $output->write($formatter->stop(spl_object_hash($process), $message, $process->isSuccessful()));
        }

        if (!$process->isSuccessful() && null !== $error) {
            $output->writeln(sprintf('<error>%s</error>', $this->escapeString($error)));
        }

        return $process;
    }

    /**
     * Runs the process.
     *
     * This is identical to run() except that an exception is thrown if the process
     * exits with a non-zero exit code.
     *
     * @param OutputInterface $output   An OutputInterface instance
     * @param string|Process  $cmd      An instance of Process or a command to run
     * @param string|null     $error    An error message that must be displayed if something went wrong
     * @param callable|null   $callback A PHP callback to run whenever there is some
     *                                  output available on STDOUT or STDERR
     *
     * @return Process The process that ran
     *
     * @throws ProcessFailedException
     *
     * @see run()
     */
    public function mustRun(OutputInterface $output, $cmd, $error = null, callable $callback = null)
    {
        $process = $this->run($output, $cmd, $error, $callback);

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }

    /**
     * Wraps a Process callback to add debugging output.
     *
     * @param OutputInterface $output   An OutputInterface interface
     * @param Process         $process  The Process
     * @param callable|null   $callback A PHP callable
     *
     * @return callable
     */
    public function wrapCallback(OutputInterface $output, Process $process, callable $callback = null)
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        $formatter = $this->getHelperSet()->get('debug_formatter');

        return function ($type, $buffer) use ($output, $process, $callback, $formatter) {
            $output->write($formatter->progress(spl_object_hash($process), $this->escapeString($buffer), Process::ERR === $type));

            if (null !== $callback) {
                $callback($type, $buffer);
            }
        };
    }

    private function escapeString($str)
    {
        return str_replace('<', '\\<', $str);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'process';
    }
}
