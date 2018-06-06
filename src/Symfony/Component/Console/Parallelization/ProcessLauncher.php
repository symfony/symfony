<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Parallelization;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * Launches a number of processes and distributes data among these processes.
 *
 * The distributed data set is passed to run(). The launcher spawns as many
 * processes as configured in the constructor. Each process receives a share
 * of the data set via its standard input, separated by newlines. The size
 * of this share can be configured in the constructor (the segment size).
 */
class ProcessLauncher
{
    /**
     * @var string
     */
    private $command;

    /**
     * @var string
     */
    private $workingDirectory;

    /**
     * @var array
     */
    private $environmentVariables;

    /**
     * @var int
     */
    private $processLimit;

    /**
     * @var int
     */
    private $segmentSize;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Process[]
     */
    private $runningProcesses = [];

    /**
     * @var callable
     */
    private $callback;

    /**
     * Creates the process launcher.
     *
     * @param string          $command              The console command to run
     * @param string          $workingDirectory     The working directory to run
     *                                              the command in
     * @param array           $environmentVariables The environment variables
     *                                              passed to the command
     * @param int             $processLimit         The maximum number of
     *                                              processes that should be
     *                                              executed at the same time
     * @param int             $segmentSize          The size of the data set
     *                                              each process receives
     * @param LoggerInterface $logger               A logger for debug output
     * @param callable        $callback             The callback that receives
     *                                              the output of the child
     *                                              processes
     */
    public function __construct(
        string $command,
        string $workingDirectory,
        array $environmentVariables,
        int $processLimit,
        int $segmentSize,
        LoggerInterface $logger,
        callable $callback
    ) {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;
        $this->environmentVariables = $environmentVariables;
        $this->processLimit = $processLimit;
        $this->segmentSize = $segmentSize;
        $this->logger = $logger;
        $this->callback = $callback;
    }

    /**
     * Runs child processes to process the given elements.
     *
     * @param string[] $elements The elements to process. None of the elements
     *                           must contain newlines
     */
    public function run(iterable $elements): void
    {
        $currentInputStream = null;
        $numberOfStreamedElements = 0;

        foreach ($elements as $element) {
            // Close the input stream if the segment is full
            if (null !== $currentInputStream && $numberOfStreamedElements >= $this->segmentSize) {
                $currentInputStream->close();

                $currentInputStream = null;
                $numberOfStreamedElements = 0;
            }

            // Wait until we can launch a new process
            while (null === $currentInputStream) {
                $this->freeTerminatedProcesses();

                if (count($this->runningProcesses) < $this->processLimit) {
                    // Start a new process
                    $currentInputStream = new InputStream();
                    $numberOfStreamedElements = 0;

                    $this->startProcess($currentInputStream);

                    break;
                }

                // 100ms
                usleep(100000);
            }

            // Stream the data segment to the process' input stream
            $currentInputStream->write($element."\n");

            ++$numberOfStreamedElements;
        }

        if (null !== $currentInputStream) {
            $currentInputStream->close();
        }

        while (count($this->runningProcesses) > 0) {
            $this->freeTerminatedProcesses();

            // 100ms
            usleep(100000);
        }
    }

    /**
     * Starts a single process reading from the given input stream.
     *
     * @param InputStream $inputStream The input stream
     */
    private function startProcess(InputStream $inputStream): void
    {
        $process = new Process(
            $this->command,
            $this->workingDirectory,
            $this->environmentVariables,
            null,
            null
        );

        $process->setInput($inputStream);
        $process->start($this->callback);

        $this->logger->debug('Command started');

        $this->runningProcesses[] = $process;
    }

    /**
     * Searches for terminated processes and removes them from memory to make
     * space for new processes.
     */
    private function freeTerminatedProcesses(): void
    {
        foreach ($this->runningProcesses as $key => $process) {
            if (!$process->isRunning()) {
                $this->logger->debug('Command finished');

                unset($this->runningProcesses[$key]);
            }
        }
    }
}
