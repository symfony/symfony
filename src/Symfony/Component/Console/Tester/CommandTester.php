<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tester;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\TestOutput;

/**
 * Eases the testing of console commands.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
class CommandTester
{
    use TesterTrait;

    private Command $command;
    private OutputFormatterInterface $outputFormatter;

    /**
     * @param OutputInterface::VERBOSITY_* $verbosity
     */
    public function __construct(
        callable|Command $command,
        private ?bool $interactive = null,
        private bool $decorated = false,
        private int $verbosity = OutputInterface::VERBOSITY_NORMAL,
        ?OutputFormatterInterface $outputFormatter = null,
    ) {
        $this->command = $command instanceof Command ? $command : new Command(null, $command);
        $this->outputFormatter = $outputFormatter ?? new OutputFormatter();
    }

    public function setInteractive(bool $interactive): void
    {
        $this->interactive = $interactive;
    }

    public function setDecorated(bool $decorated): void
    {
        $this->decorated = $decorated;
    }

    /**
     * @param OutputInterface::VERBOSITY_* $level
     */
    public function setVerbosity(int $level): void
    {
        $this->verbosity = $level;
    }

    public function setOutputFormatter(OutputFormatterInterface $outputFormatter): void
    {
        $this->outputFormatter = $outputFormatter;
    }

    /**
     * Runs the command with the result-based testing API.
     *
     * This method is intended for new tests and returns an ExecutionResult,
     * which exposes output, error output and combined display in a single object.
     *
     * Unlike execute(), this method does not rely on state read back from TesterTrait.
     *
     * @param array                           $input             An array of command arguments and options
     * @param string[]                        $interactiveInputs An array of strings representing each input passed to the command input stream
     * @param OutputInterface::VERBOSITY_*    $verbosity
     * @param array<\Closure(string): string> $normalizers
     */
    public function run(array $input = [], array $interactiveInputs = [], ?bool $interactive = null, ?bool $decorated = null, ?int $verbosity = null, array $normalizers = []): ExecutionResult
    {
        $input = $this->createInput($input, $interactiveInputs, $interactive);
        $testOutput = new TestOutput($decorated ?? $this->decorated, $verbosity ?? $this->verbosity, $this->outputFormatter);
        $statusCode = $this->command->run($input, $testOutput);

        return ExecutionResult::fromExecution($input, $statusCode, $testOutput, $normalizers);
    }

    /**
     * Executes the command with the legacy stateful testing API.
     *
     * Use this method when interacting with the historical TesterTrait-based API,
     * e.g. getDisplay(), getErrorOutput(), getStatusCode() and assertCommandIsSuccessful().
     *
     * Prefer run() for new tests, as it returns an ExecutionResult object with
     * explicit output streams and dedicated assertions.
     *
     * Available execution options:
     *
     *  * interactive:               Sets the input interactive flag
     *  * decorated:                 Sets the output decorated flag
     *  * verbosity:                 Sets the output verbosity flag
     *  * capture_stderr_separately: Make output of stdOut and stdErr separately available
     *
     * @param array $input   An array of command arguments and options
     * @param array $options An array of execution options
     *
     * @return int The command exit code
     */
    public function execute(array $input, array $options = []): int
    {
        $this->input = $this->createInput($input, $this->inputs, $options['interactive'] ?? $this->interactive);

        if (!isset($options['decorated'])) {
            $options['decorated'] = $this->decorated;
        }

        $this->initOutput($options);

        return $this->statusCode = $this->command->run($this->input, $this->output);
    }

    private function createInput(array $input, array $interactiveInputs = [], ?bool $interactive = null): InputInterface
    {
        if (!isset($input['command']) && $this->command->getApplication()?->getDefinition()->hasArgument('command')) {
            $input = array_merge(['command' => $this->command->getName()], $input);
        }

        $input = new ArrayInput($input);
        // Use an in-memory input stream even if no inputs are set so that QuestionHelper::ask() does not rely on the blocking STDIN.
        $input->setStream(self::createStream($interactiveInputs));

        if (null !== $interactive ??= $this->interactive) {
            $input->setInteractive($interactive);
        }

        return $input;
    }
}
