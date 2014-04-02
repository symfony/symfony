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

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * The Process class provides helpers to run external processes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProcessHelper extends Helper
{
    /**
     * Runs an external process.
     *
     * @param OutputInterface $output   An OutputInterface instance
     * @param string|Process  $cmd      An instance of Process or a command to run
     * @param string|null     $error    An error message that must be displayed if something went wrong
     * @param callback|null   $callback A PHP callback to run whenever there is some
     *                                  output available on STDOUT or STDERR
     *
     * @return Process The process that ran
     */
    public function run(OutputInterface $output, $cmd, $error = null, $callback = null)
    {
        $verbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE;
        $debug = $output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;

        $formatter = $this->getHelperSet()->get('debug_formatter');

        $process = $cmd instanceof Process ? $cmd : new Process($cmd);

        if ($verbose) {
            $output->write($formatter->start(spl_object_hash($process), $process->getCommandLine()));
        }

        if ($debug) {
            $callback = $this->wrapCallback($output, $process, $callback);
        }

        $process->run($callback);

        if ($verbose) {
            $message = $process->isSuccessful() ? 'Command ran successfully' : sprintf('%s Command did not run sucessfully', $process->getExitCode());
            $output->write($formatter->stop(spl_object_hash($process), $message, $process->isSuccessful()));
        }

        if (!$process->isSuccessful() && null !== $error) {
            $output->writeln(sprintf('<error>%s</error>'), $error);
        }

        return $process;
    }

    /**
     * Wraps a Process callback to add debugging output.
     *
     * @param OutputInterface $output   An OutputInterface interface
     * @param callable|null   $callback A PHP callable
     */
    public function wrapCallback(OutputInterface $output, Process $process, $callback = null)
    {
        $formatter = $this->getHelperSet()->get('debug_formatter');

        return function ($type, $buffer) use ($output, $process, $callback, $formatter) {
            $output->write($formatter->progress(spl_object_hash($process), $buffer, 'err' === $type));

            if (null !== $callback) {
                $callback($type, $buffer);
            }
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'process';
    }
}
