<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Runs commands in a child process.
 *
 * @author Gordon Franke <g.franke@searchmetrics.com>
 */
class ForkManager
{
    /**
     * start microtime
     *
     * @var int
     */
    private $start = null;

    /**
     * finish microtime
     *
     * @var int
     */
    private $finish = null;

    /**
     * command queue
     *
     * @var array
     */
    private $queue = array();

    /**
     * Store the actual running pids
     *
     * @var array array with pid ids
     */
    private $pids = array();

    /**
     * Constructor.
     */
    public function __construct()
    {
        if (!extension_loaded('pcntl')) {
            throw new \RuntimeException('PCNTL extension is not enabled.');
        }
    }

    /**
     * Adds command to queue.
     * 
     * @param Command         $command command object to execute
     * @param InputInterface  $input   input object for command
     * @param OutputInterface $output  output object for command
     *
     * @return int the internal identifier
     */
    public function addCommand(Command $command, InputInterface $input, OutputInterface $output)
    {
        $this->queue[] = array($command, $input, $output);

        return count($this->queue) - 1;
    }

    /**
     * Run all commands and wait on finish.
     *
     * @param OutputInterface $output output object to use
     */
    public function run(OutputInterface $output)
    {
        $this->runSilence($output);
        while (!$this->isFinish()) {
            usleep(100);
        }

        $output->writeln(sprintf('<comment>ForkManager:</comment> <info>finish</info> total elapsed <info>%u</info> sec', $this->getTotalElapsedTime()));
    }

    /**
     * Reset the object for reuse.
     */
    public function reset()
    {
        $this->start = null;
        $this->finish = null;
    }

    /**
     * Run all commands.
     *
     * @param OutputInterface $output output object to use
     */
    public function runSilence(OutputInterface $output)
    {
        if (null === $this->start) {
            $this->start = microtime(true);
        }

        $output->writeln(sprintf('<comment>ForkManager:</comment> call <info>%u</info> commands in seperate child process', count($this->queue)));
        foreach ($this->queue as $key => $command) {
            list($command, $input, $output) = $command;
            unset($this->queue[$key]);

            $output->writeln(sprintf('<comment>ForkManager:</comment> fork for <info>%s</info> command', $command->getName()));
            $pid = pcntl_fork();
            if (-1 === $pid) {
                throw new \RuntimeException('Could not fork child process');
            } elseif ($pid) {
                $this->pids[] = $pid;
            } else {
                $command->run($input, $output);
                $output->writeln('<comment>ForkManager:</comment> child process <info>finish</info>');
                $this->finish = microtime(true);
                exit(0);
            }
        }
    }

    /**
     * Checks if all or specific command is finish.
     *
     * @param integer $position command position to check
     *
     * @return bool 
     */
    public function isFinish($number = null)
    {
        $this->checkChilds();
        if (null !== $number) {
            return isset($this->pids[$number - 1]);
        }

        return empty($this->pids);
    }

    /**
     * Gets the total elapsed time for the last run.
     * 
     * @return float
     */
    public function getTotalElapsedTime()
    {
        $end = $this->finish;
        if (null === $end) {
            $end = microtime(true);
        }

        return $end - $this->start;
    }

    /**
     * Checks each child process if finish.
     */
    private function checkChilds()
    {
        while (true) {
            $waitPid = pcntl_waitpid(-1, $status, WNOHANG);
            if ($waitPid < 1) {
                break;
            }

            foreach ($this->pids as $key => $pid) {
                if ($waitPid === $pid) {
                    unset($this->pids[$key]);
                }
            }
        }        
    }
}