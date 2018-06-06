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

use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Adds parallelization capabilities to console commands.
 *
 * Make sure to call configureParallelization() in your configure() method!
 *
 * You must implement the following methods in your command:
 *
 *  * fetchElements(): Returns all the elements that you want to process as
 *    strings. Typically, you will return IDs of database objects here.
 *  * runSingleCommand(): Executes the command for a single element.
 *  * getElementName(): Returns a human readable name of the processed things
 *
 * You can improve the performance of your command by making use of batching.
 * Batching allows you to process multiple elements together, for example to
 * persist them in a batch to reduce the number of I/O operations.
 *
 * To enable batching, you will typically implement runAfterBatch() and persist
 * the changes done in multiple calls of runSingleCommand().
 *
 * The batch size is determined by getBatchSize() and defaults to the segment
 * size. The segment size is the number of elements a worker (child) process
 * consumes before it dies. This means that, by default, a child process will
 * process all its elements, persist them in a batch and then die. If you want
 * to improve the performance of your command, try to tweak getSegmentSize()
 * first. Optionally, you can tweak getBatchSize() to process multiple batches
 * in each child process.
 */
trait Parallelization
{
    /**
     * Returns the symbol for communicating progress from the child to the
     * main process.
     *
     * @return string A single character
     */
    private static function getProgressSymbol()
    {
        return chr(254);
    }

    /**
     * Detects the path of the PHP interpreter.
     *
     * @throws RuntimeException If PHP could not be found
     *
     * @return string The absolute path to the PHP interpreter
     */
    private static function detectPhpExecutable(): string
    {
        $executableFinder = new PhpExecutableFinder();

        $php = $executableFinder->find();

        if (false === $php) {
            throw new RuntimeException('Cannot find php executable');
        }

        return $php;
    }

    /**
     * Returns the environment variables that are passed to the child processes.
     *
     * @param ContainerInterface $container The service containers
     *
     * @return string[] A list of environment variable names and values
     */
    private static function getEnvironmentVariables(ContainerInterface $container): array
    {
        return [
            'PATH' => getenv('PATH'),
            'HOME' => getenv('HOME'),
            'SYMFONY_DEBUG' => $container->getParameter('kernel.debug'),
            'SYMFONY_ENV' => $container->getParameter('kernel.environment'),
        ];
    }

    /**
     * Returns the working directory for the child process.
     *
     * @param ContainerInterface $container The service container
     *
     * @return string The absolute path to the working directory
     */
    private static function getWorkingDirectory(ContainerInterface $container): string
    {
        return dirname($container->getParameter('kernel.root_dir'));
    }

    /**
     * Provided by Symfony Command class.
     *
     * @return string The command name
     */
    abstract public function getName();

    /**
     * Provided by Symfony Command class.
     *
     * @return ContainerInterface The service container
     */
    abstract protected function getContainer();

    /**
     * Provided by Symfony Command class.
     *
     * @return Application The console application
     */
    abstract protected function getApplication();

    /**
     * Fetches the elements that should be processed.
     *
     * Typically, you will fetch all the elements of the database objects that you
     * you want to process here. These will be passed to runSingleCommand().
     *
     * This method is called exactly once in the main process.
     *
     * @param InputInterface $input The console input
     *
     * @return string[] The elements to process
     */
    abstract protected function fetchElements(InputInterface $input): iterable;

    /**
     * Processes an element in the child process.
     *
     * @param string          $element The element to process
     * @param InputInterface  $input   The console input
     * @param OutputInterface $output  The console output
     */
    abstract protected function runSingleCommand(string $element, InputInterface $input, OutputInterface $output);

    /**
     * Returns the name of each element in lowercase letters.
     *
     * For example, this method could return "contact" if the count is one and
     * "contacts" otherwise.
     *
     * @param int $count The number of elements
     *
     * @return string The name of the element in the correct plurality
     */
    abstract protected function getElementName(int $count);

    /**
     * Can be overridden to execute logic in the very beginning.
     *
     * This method is always executed in the main process.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     */
    protected function runBeforeFirstCommand(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * Can be overridden to execute logic in the very end.
     *
     * This method is always executed in the main process.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     */
    protected function runAfterLastCommand(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * Can be overridden to execute logic before every batch.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     */
    protected function runBeforeBatch(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * Can be overridden to execute logic after every batch.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     */
    protected function runAfterBatch(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * Returns the number of elements to process per child process.
     *
     * You can override this method to tweak the performance of your
     * parallelized command.
     *
     * @return int The number of elements to process per child process
     */
    protected function getSegmentSize(): int
    {
        return 50;
    }

    /**
     * Returns the number of elements to process in a batch.
     *
     * Batches allow you to persist multiple elements at once. Add
     * your persistence logic to runAfterBatch().
     *
     * @return int The number of elements to process in a batch
     */
    protected function getBatchSize(): int
    {
        return $this->getSegmentSize();
    }

    /**
     * Executes the parallelized command.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('child')) {
            return $this->executeChildProcess($input, $output);
        }

        $this->executeMainProcess($input, $output);
    }

    /**
     * Adds the command configuration specific to parallelization.
     *
     * Call this method in your configure() method.
     */
    protected function configureParallelization()
    {
        $this
            ->addArgument(
                'element',
                InputArgument::OPTIONAL,
                'The element to process'
            )
            ->addOption(
                'processes',
                'p',
                InputOption::VALUE_OPTIONAL,
                'The number of parallel processes to run',
                1
            )
            ->addOption(
                'child',
                null,
                InputOption::VALUE_NONE,
                'Set on child processes'
            )
        ;
    }

    /**
     * Executes the main process.
     *
     * The main process spawns as many child processes as set in the
     * "--processes" option. Each of the child processes receives a segment of
     * items of the processed data set and terminates. As long as there is data
     * left to process, new child processes are spawned automatically.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     */
    protected function executeMainProcess(InputInterface $input, OutputInterface $output)
    {
        $this->runBeforeFirstCommand($input, $output);

        $numberOfProcesses = (int) $input->getOption('processes');
        $elements = $input->getArgument('element') ? [$input->getArgument('element')] : $this->fetchElements($input);
        $count = count($elements);
        $segmentSize = 1 === $numberOfProcesses ? $count : $this->getSegmentSize();
        $batchSize = $this->getBatchSize();
        $rounds = 1 === $numberOfProcesses ? 1 : ceil($count * 1.0 / $segmentSize);
        $batches = ceil($segmentSize * 1.0 / $batchSize) * $rounds;

        if (0 === $numberOfProcesses) {
            throw new InvalidArgumentException(sprintf('Requires at least one process, "%s" given.', $input->getOption('processes')));
        }

        $output->writeln(sprintf(
            'Processing %d %s in segments of %d, batches of %d, %d %s, %d %s in %d %s',
            $count,
            $this->getElementName($count),
            $segmentSize,
            $batchSize,
            $rounds,
            1 === $rounds ? 'round' : 'rounds',
            $batches,
            1 === $batches ? 'batch' : 'batches',
            $numberOfProcesses,
            1 === $numberOfProcesses ? 'process' : 'processes'
        ));
        $output->writeln('');

        $progressBar = new ProgressBar($output, $count);
        $progressBar->setFormat('debug');
        $progressBar->start();

        if ($count <= $segmentSize || 1 === $numberOfProcesses) {
            $i = 0;

            // Run in main process if we have only one segment
            foreach ($elements as $element) {
                if (0 === $i) {
                    $this->runBeforeBatch($input, $output);
                }

                $this->runTolerantSingleCommand($element, $input, $output);

                $progressBar->advance();
                ++$i;

                if ($i >= $this->getBatchSize()) {
                    $this->runAfterBatch($input, $output);
                    $i = 0;
                }
            }

            if (0 !== $i) {
                $this->runAfterBatch($input, $output);
            }
        } else {
            // Distribute if we have multiple segments
            $commandTemplate = sprintf('%s bin/console %s %s --child --env=%s --verbose --no-debug',
                self::detectPhpExecutable(),
                $this->getName(),
                implode(' ', array_slice($input->getArguments(), 1)),
                $input->getOption('env')
            );
            $terminalWidth = current($this->getApplication()->getTerminalDimensions());

            $processLauncher = new ProcessLauncher(
                $commandTemplate,
                self::getWorkingDirectory($this->getContainer()),
                self::getEnvironmentVariables($this->getContainer()),
                $numberOfProcesses,
                $segmentSize,
                $this->getContainer()->get('logger'),
                function (string $type, string $buffer) use ($progressBar, $output, $terminalWidth) {
                    $this->processChildOutput($buffer, $progressBar, $output, $terminalWidth);
                }
            );

            $processLauncher->run($elements);
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('');
        $output->writeln(sprintf(
            'Processed %d %s.',
            $count,
            $this->getElementName($count)
        ));

        $this->runAfterLastCommand($input, $output);
    }

    /**
     * Executes the child process.
     *
     * This method reads the elements from the standard input that the main process
     * piped into the process. These elements are passed to runSingleCommand() one
     * by one.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     */
    protected function executeChildProcess(InputInterface $input, OutputInterface $output)
    {
        $advancementChar = self::getProgressSymbol();
        $handle = fopen('php://stdin', 'r');
        $i = 0;

        while (false !== $line = fgets($handle)) {
            if (0 === $i) {
                $this->runBeforeBatch($input, $output);
            }

            $this->runTolerantSingleCommand($line, $input, $output);

            // Communicate progress to the main process
            $output->write($advancementChar);

            ++$i;

            if ($i >= $this->getBatchSize()) {
                $this->runAfterBatch($input, $output);
                $i = 0;
            }
        }

        if (0 !== $i) {
            $this->runAfterBatch($input, $output);
        }

        fclose($handle);
    }

    /**
     * Called whenever data is received in the main process from a child process.
     *
     * @param string          $buffer        The received data
     * @param ProgressBar     $progressBar   The progress bar
     * @param OutputInterface $output        The output of the main process
     * @param int             $terminalWidth The width of the terminal window
     *                                       in characters
     */
    private function processChildOutput(string $buffer, ProgressBar $progressBar, OutputInterface $output, int $terminalWidth)
    {
        $advancementChar = $this->getProgressSymbol();
        $chars = mb_substr_count($buffer, $advancementChar);

        // Display unexpected output
        if ($chars !== mb_strlen($buffer)) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<comment>%s</comment>',
                str_pad(' Process Output ', $terminalWidth, '=', STR_PAD_BOTH)
            ));
            $output->writeln(str_replace($advancementChar, '', $buffer));
            $output->writeln('');
        }

        $progressBar->advance($chars);
    }

    private function runTolerantSingleCommand(string $element, InputInterface $input, OutputInterface $output): void
    {
        try {
            $this->runSingleCommand(trim($element), $input, $output);
        } catch (Exception $exception) {
            $output->writeln(sprintf(
                "Failed to process \"%s\": %s\n%s",
                trim($element),
                $exception->getMessage(),
                $exception->getTraceAsString()
            ));

            $this->getContainer()->reset();
        }
    }
}
