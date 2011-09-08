<?php
namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Run commands in child process
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

    public function __construct()
    {
    }

    /**
     * Add command to queue
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
     * Run all commands and wait on finish
     *
     * @param OutputInterface $output output object to use
     */
    public function run(OutputInterface $output) {
        $this->runSilence($output);
        while(!$this->isFinish()) {
            usleep(100);
        }

        $output->writeln(sprintf('<comment>ForkManager:</comment> <info>finish</info> total elapsed <info>%u</info> sec', $this->getTotalElapsedTime()));
    }

    /**
     * Reset the object for reuse
     */
    public function reset() {
        $this->start = null;
        $this->finish = null;
    }

    /**
     * Run all commands
     *
     * @param utputInterface $output output object to use
     */
    public function runSilence(OutputInterface $output)
    {
        if ($this->start === null) {
            $this->start = microtime(true);
        }

        $output->writeln(sprintf('<comment>ForkManager:</comment> call <info>%u</info> commands in seperate child process', count($this->queue)));
        foreach ($this->queue as $key => $command)
        {
            list($command, $input, $output) = $command;
            unset($this->queue[$key]);

            $output->writeln(sprintf('<comment>ForkManager:</comment> fork for <info>%s</info> command', $command->getName()));
            $pid = pcntl_fork();
            if ($pid == -1)
            {
                throw new \RuntimeException('Could not fork child process');
            }
            else if ($pid) // parent
            {
                $this->pids[] = $pid;
            }
            else // child
            {
                $command->run($input, $output);
                $output->writeln('<comment>ForkManager:</comment> child process <info>finish</info>');
                $this->finish = microtime(true);
                exit(0);
            }
        }

        return $this;
    }

    /**
     * Check if all or specific command is finish
     * @param int $position command position to check
     * @return bool 
     */
    public function isFinish($number = null)
    {
        $this->checkChilds();
        if ($number !== null)
        {
            return isset($this->pids[$number - 1]);
        }

        return empty($this->pids);
    }

    /**
     * Check if all or specific command is finish
     * @param int $position command position to check
     * @return bool 
     */
    public function getTotalElapsedTime()
    {
        $end = $this->finish;
        if ($end === null) {
            $end = microtime(true);
        }
        return $end - $this->start;
    }

    /**
     * Check each child process if finish
     */
    private function checkChilds()
    {
        while(true) {
            $waitpid = pcntl_waitpid(-1, $status, WNOHANG);
            if ($waitpid < 1 OR $waitpid === null) {
                break;
            }

            foreach($this->pids as $key => $pid) {
                if ($waitpid == $pid) {
                    unset($this->pids[$key]);
                }
            }
        }        
    }
}