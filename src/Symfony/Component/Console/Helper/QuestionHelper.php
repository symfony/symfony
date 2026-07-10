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

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Event\QuestionAnsweredEvent;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\FileQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Symfony\Component\String\s;

/**
 * The QuestionHelper class provides helpers to interact with the user.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class QuestionHelper extends Helper
{
    private static bool $stty = true;
    private static bool $stdinIsInteractive;

    public function __construct(
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /**
     * Asks a question to the user.
     *
     * @return mixed The user answer
     *
     * @throws RuntimeException If there is no data to read in the input stream
     */
    public function ask(InputInterface $input, OutputInterface $output, Question $question): mixed
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        if (!$input->isInteractive()) {
            return $this->getDefaultAnswer($question);
        }

        $inputStream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
        $inputStream ??= \STDIN;

        ProgressBar::pauseAll();

        try {
            if (!$question->getValidator() && !$question->getConstraints()) {
                return $this->doAsk($inputStream, $output, $question);
            }

            $interviewer = fn () => $this->doAsk($inputStream, $output, $question);

            return $this->validateAttempts($interviewer, $output, $question);
        } catch (MissingInputException $exception) {
            $input->setInteractive(false);

            if (null === $fallbackOutput = $this->getDefaultAnswer($question)) {
                throw $exception;
            }

            return $fallbackOutput;
        } finally {
            ProgressBar::resumeAll();
        }
    }

    public function getName(): string
    {
        return 'question';
    }

    /**
     * Prevents usage of stty.
     */
    public static function disableStty(): void
    {
        self::$stty = false;
    }

    /**
     * Asks the question to the user.
     *
     * @param resource $inputStream
     *
     * @throws RuntimeException In case the fallback is deactivated and the response cannot be hidden
     */
    private function doAsk($inputStream, OutputInterface $output, Question $question): mixed
    {
        if ($question instanceof FileQuestion) {
            $this->writePrompt($output, $question);

            return (new FileInputHelper())->readFileInput($inputStream, $output, $question);
        }

        $this->writePrompt($output, $question);

        $autocomplete = $question->getAutocompleterCallback();

        if (null === $autocomplete || !self::$stty || !Terminal::hasSttyAvailable()) {
            $ret = false;
            if ($question->isHidden()) {
                try {
                    $hiddenResponse = $this->getHiddenResponse($output, $inputStream, $question->isTrimmable());
                    $ret = $question->isTrimmable() ? trim($hiddenResponse) : $hiddenResponse;
                } catch (RuntimeException $e) {
                    if (!$question->isHiddenFallback()) {
                        throw $e;
                    }
                }
            }

            if (false === $ret) {
                $isBlocked = stream_get_meta_data($inputStream)['blocked'] ?? true;

                if (!$isBlocked) {
                    stream_set_blocking($inputStream, true);
                }

                $ret = $this->readInput($inputStream, $output, $question);

                if (!$isBlocked) {
                    stream_set_blocking($inputStream, false);
                }

                if (false === $ret) {
                    throw new MissingInputException('Aborted.');
                }
                if ($question->isTrimmable()) {
                    $ret = trim($ret);
                }
            }
        } else {
            $autocomplete = $this->autocomplete($output, $question, $inputStream, $autocomplete);
            $ret = $question->isTrimmable() ? trim($autocomplete) : $autocomplete;
        }

        if ($output instanceof ConsoleSectionOutput) {
            $output->addContent(''); // add EOL to the question
            $output->addContent($ret);
        }

        $ret = \strlen($ret) > 0 ? $ret : $question->getDefault();

        if ($normalizer = $question->getNormalizer()) {
            return $normalizer($ret);
        }

        return $ret;
    }

    private function getDefaultAnswer(Question $question): mixed
    {
        $default = $question->getDefault();

        if (null === $default) {
            return $default;
        }

        if ($validator = $question->getValidator()) {
            return \call_user_func($validator, $default);
        } elseif ($question instanceof ChoiceQuestion) {
            $choices = $question->getChoices();

            if (!$question->isMultiselect()) {
                return $choices[$default] ?? $default;
            }

            $default = explode(',', $default);
            foreach ($default as $k => $v) {
                $v = $question->isTrimmable() ? trim($v) : $v;
                $default[$k] = $choices[$v] ?? $v;
            }
        }

        return $default;
    }

    /**
     * Outputs the question prompt.
     */
    protected function writePrompt(OutputInterface $output, Question $question): void
    {
        $message = $question->getQuestion();

        if ($question instanceof ChoiceQuestion) {
            $output->writeln(array_merge([
                $question->getQuestion(),
            ], $this->formatChoiceQuestionChoices($question, 'info')));

            $message = $question->getPrompt();
        }

        $output->write($message);
    }

    /**
     * @return string[]
     */
    protected function formatChoiceQuestionChoices(ChoiceQuestion $question, string $tag): array
    {
        $messages = [];

        $maxWidth = max(array_map([__CLASS__, 'width'], array_keys($choices = $question->getChoices())));

        foreach ($choices as $key => $value) {
            $padding = str_repeat(' ', $maxWidth - self::width($key));

            $messages[] = \sprintf("  [<$tag>%s$padding</$tag>] %s", $key, $value);
        }

        return $messages;
    }

    /**
     * Outputs an error message.
     */
    protected function writeError(OutputInterface $output, \Exception $error): void
    {
        if (null !== $this->getHelperSet() && $this->getHelperSet()->has('formatter')) {
            $message = $this->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error');
        } else {
            $message = '<error>'.$error->getMessage().'</error>';
        }

        $output->writeln($message);
    }

    private function runInputEngine($inputStream, OutputInterface $output, array $customHandlers = [], ?\stdClass $state = null): string
    {
        $cursor = new Cursor($output, $inputStream);
        $helper ??= new TerminalInputHelper($inputStream);

        $state ??= new \stdClass();
        $state->buffer ??= '';
        $state->offset ??= 0; // Represents how many characters FROM THE RIGHT the cursor is
        $state->done ??= false;

        $defaultHandlers = [
            // Ctrl+A: Go to beginning
            "\001" => static function ($state, $cursor) {
                $steps = (mb_strlen($state->buffer) - $state->offset);
                if ($steps > 0) {
                    $cursor->moveLeft(mb_strlen($state->buffer) - $state->offset);
                }
                $state->offset = mb_strlen($state->buffer);
            },
            // Ctrl+E: Go to end
            "\005" => static function ($state, $cursor) {
                if ($state->offset > 0) {
                    $cursor->moveRight($state->offset);
                }
                $state->offset = 0;
            },
            // Left Arrow
            "\033[D" => static function ($state, $cursor) {
                if ($state->offset < mb_strlen($state->buffer)) {
                    $cursor->moveLeft(1);
                    ++$state->offset;
                }
            },
            // Right Arrow
            "\033[C" => static function ($state, $cursor) {
                if ($state->offset > 0) {
                    $cursor->moveRight(1);
                    --$state->offset;
                }
            },
            "\n" => static function ($state, $cursor, $output) {
                $output->write("\n");
                $state->done = true;
            },
            // Backspace
            "\177" => static function ($state, $cursor, $output) {
                $length = mb_strlen($state->buffer);

                // If the cursor is at the very beginning of the line, backspace does nothing
                if ($state->offset === $length) {
                    return;
                }

                // Calculate our index from the left
                $cursorPosition = $length - $state->offset;

                // Get the character being deleted to determine its terminal width
                $charToDelete = mb_substr($state->buffer, $cursorPosition - 1, 1);
                $charWidth = \Symfony\Component\String\s($charToDelete)->width(false);

                // Split the string, explicitly skipping the 1 character behind the cursor
                $leftPart = mb_substr($state->buffer, 0, $cursorPosition - 1);
                $rightPart = mb_substr($state->buffer, $cursorPosition);

                // Mutate the state
                $state->buffer = $leftPart.$rightPart;

                // Visually move the cursor back by the character's width
                $cursor->moveLeft($charWidth);
                $cursor->savePosition();

                // Print the right half of the string to shift everything left on screen
                $output->write($rightPart);

                // Erase the leftover "ghost" character at the far right end
                $cursor->clearLineAfter();

                // Snap the cursor back to the user's editing point
                $cursor->restorePosition();
            },

            // Default character insertion
            'default' => static function ($char, $state, $cursor, $output) {
                $length = mb_strlen($state->buffer);
                $cursorPosition = $length - $state->offset;

                // Split the string and inject the new character in the middle
                $leftPart = mb_substr($state->buffer, 0, $cursorPosition);
                $rightPart = mb_substr($state->buffer, $cursorPosition);

                // Mutate the state
                $state->buffer = $leftPart.$char.$rightPart;

                // Visually print the new character (this implicitly moves the terminal cursor right by 1)
                $output->write($char);

                // If we are at the end of the string, we don't need to redraw anything else
                if (0 === $state->offset) {
                    return;
                }

                // Otherwise, save the cursor position, draw the rest of the string, and snap back
                $cursor->savePosition();
                $output->write($rightPart);
                $cursor->clearLineAfter();
                $cursor->restorePosition();
            },
        ];

        $handlers = array_merge($defaultHandlers, $customHandlers);

        shell_exec('stty -icanon -echo');

        try {
            while (!feof($inputStream)) {
                $helper->waitForInput();
                $c = fread($inputStream, 1);

                if (false === $c || ('' === $state->buffer && '' === $c)) {
                    throw new MissingInputException('Aborted.');
                }

                // Resolve Escape Sequences (Arrows)
                if ("\033" === $c) {
                    $c .= fread($inputStream, 1);
                    if (isset($c[1]) && '[' === $c[1]) {
                        do {
                            $char = fread($inputStream, 1);
                            $c .= $char;
                        } while ('' !== $char && !preg_match('/[\x40-\x7E]/', $char) && !feof($inputStream));
                    }
                }
                // Resolve Multi-byte UTF-8
                elseif ("\x80" <= $c) {
                    $c .= fread($inputStream, ["\xC0" => 1, "\xD0" => 1, "\xE0" => 2, "\xF0" => 3][$c & "\xF0"]);
                }
                // Dispatch Event
                if (isset($handlers[$c])) {
                    $handlers[$c]($state, $cursor, $output);
                } elseif (!str_starts_with($c, "\033") && \ord($c) >= 32) {
                    $handlers['default']($c, $state, $cursor, $output);
                }
                if ($state->done) {
                    break;
                }
            }
        } finally {
            // Restore terminal to normal! (No minus signs)
            shell_exec('stty icanon echo');
            $helper->finish();
        }

        return $state->buffer;
    }

    private function redrawAutocompleteGhostText($state, $cursor, $output, Question $question): void
    {
        // Only draw ghost text if the cursor is at the end of the line
        if ($state->offset > 0) {
            return;
        }

        $numMatches = \count($state->matches);

        if ($numMatches > 0 && -1 !== $state->matchIndex) {
            $cursor->savePosition();

            $mostRecentValue = $this->mostRecentlyEnteredValue($state->buffer);
            $charactersEntered = \strlen($mostRecentValue);

            // Extract the remaining matching text snippet
            $suggestionTail = substr($state->matches[$state->matchIndex], $charactersEntered);
            $escapedGhostText = OutputFormatter::escapeTrailingBackslash($suggestionTail);

            // Write out the visual suggestion in standard inverted block colors
            $output->write('<hl>'.$escapedGhostText.'</hl>');

            $cursor->restorePosition();
        }
    }

    /**
     * Autocompletes a question.
     *
     * @param resource $inputStream
     *
     * @param-immediately-invoked-callable $autocomplete
     */
    private function autocomplete(OutputInterface $output, Question $question, $inputStream, callable $autocompleteCallback): string
    {
        // 1. Setup the initial state
        $state = new \stdClass();
        $state->buffer = '';
        $state->offset = 0;
        $state->done = false;
        $state->ret = '';
        $state->matches = $autocompleteCallback($state->ret);
        $state->matchIndex = -1;

        // Add highlighted text style to terminal output
        $output->getFormatter()->setStyle('hl', new OutputFormatterStyle('black', 'white'));

        // 2. Define custom key handlers for the Autocomplete State Machine
        $autocompleteHandlers = [
            // Up Arrow: Cycle backwards through suggestions
            "\033[A" => function ($state, $cursor, $output) use ($question) {
                $numMatches = \count($state->matches);
                if (0 === $numMatches) {
                    return;
                }

                if (-1 === $state->matchIndex) {
                    $state->matchIndex = 0;
                }

                $state->matchIndex = ($numMatches + $state->matchIndex - 1) % $numMatches;
                $this->redrawAutocompleteGhostText($state, $cursor, $output, $question);
            },

            // Down Arrow: Cycle forwards through suggestions
            "\033[B" => function ($state, $cursor, $output) use ($question) {
                $numMatches = \count($state->matches);
                if (0 === $numMatches) {
                    return;
                }

                $state->matchIndex = ($state->matchIndex + 1) % $numMatches;
                $this->redrawAutocompleteGhostText($state, $cursor, $output, $question);
            },

            // Tab Key: Complete the current selection
            "\t" => function ($state, $cursor, $output) use ($autocompleteCallback) {
                $numMatches = \count($state->matches);
                if ($numMatches > 0 && -1 !== $state->matchIndex) {
                    if ($state->offset > 0) {
                        $cursor->moveRight($state->offset);
                        $state->offset = 0;
                    }
                    $oldBuffer = $state->buffer;
                    $state->buffer = (string) $state->matches[$state->matchIndex];

                    // Write the completed characters to the terminal screen
                    $remaining = substr($state->buffer, \strlen($this->mostRecentlyEnteredValue($oldBuffer)));
                    $output->write($remaining);

                    // Refresh matches for multi-select scenario
                    $state->ret = $state->buffer;
                    $state->matches = array_filter(
                        $autocompleteCallback($state->ret),
                        static fn ($match) => '' === $state->ret || str_starts_with($match, $state->ret)
                    );
                    $state->matchIndex = -1;
                }
            },

            // Backspace
            "\177" => function ($state, $cursor, $output) use ($question, $autocompleteCallback) {
                $length = mb_strlen($state->buffer);
                if ($state->offset === $length) {
                    return;
                }

                $cursorPosition = $length - $state->offset;
                $charToDelete = mb_substr($state->buffer, $cursorPosition - 1, 1);
                $charWidth = \Symfony\Component\String\s($charToDelete)->width(false);

                $leftPart = mb_substr($state->buffer, 0, $cursorPosition - 1);
                $rightPart = mb_substr($state->buffer, $cursorPosition);

                $state->buffer = $leftPart.$rightPart;
                $state->ret = $state->buffer; // Keep synchronized for standard filtering

                // Reset matching context
                $state->matches = $autocompleteCallback($state->ret);
                $state->matchIndex = (0 === mb_strlen($state->ret)) ? -1 : 0;

                $cursor->moveLeft($charWidth);
                $cursor->savePosition();
                $output->write($rightPart);
                $cursor->clearLineAfter();
                $cursor->restorePosition();

                $this->redrawAutocompleteGhostText($state, $cursor, $output, $question);
            },

            // Enter Key
            "\n" => function ($state, $cursor, $output) {
                $numMatches = \count($state->matches);

                // If the user presses enter while highlighting a suggestion, accept it first
                if ($numMatches > 0 && -1 !== $state->matchIndex) {
                    if ($state->offset > 0) {
                        $cursor->moveRight($state->offset);
                        $state->offset = 0;
                    }
                    $oldBuffer = $state->buffer;
                    $state->buffer = (string) $state->matches[$state->matchIndex];
                    $remaining = substr($state->buffer, \strlen($this->mostRecentlyEnteredValue($oldBuffer)));
                    $output->write($remaining);
                }

                $output->write("\n");
                $state->done = true;
            },

            // Default: Character input logic
            'default' => function ($char, $state, $cursor, $output) use ($question, $autocompleteCallback) {
                $length = mb_strlen($state->buffer);
                $cursorPosition = $length - $state->offset;

                $leftPart = mb_substr($state->buffer, 0, $cursorPosition);
                $rightPart = mb_substr($state->buffer, $cursorPosition);

                $state->buffer = $leftPart.$char.$rightPart;
                $state->ret = $state->buffer;

                $output->write($char);

                if ($state->offset > 0) {
                    $cursor->savePosition();
                    $output->write($rightPart);
                    $cursor->clearLineAfter();
                    $cursor->restorePosition();
                }

                $tempRet = $state->ret;
                if ($question instanceof ChoiceQuestion && $question->isMultiselect()) {
                    $tempRet = $this->mostRecentlyEnteredValue($state->buffer);
                }

                // Recalculate completions based on fresh typing
                $state->matches = [];
                $state->matchIndex = 0;

                foreach ($autocompleteCallback($state->ret) as $value) {
                    if (str_starts_with($value, $tempRet)) {
                        $state->matches[] = $value;
                    }
                }

                if (empty($state->matches)) {
                    $state->matchIndex = -1;
                }

                if (0 === $state->offset) {
                    $cursor->clearLineAfter();
                }
                $this->redrawAutocompleteGhostText($state, $cursor, $output, $question);
            },
        ];

        // 3. Kick off your engine wrapper using the custom handler payload!
        return $this->runInputEngine($inputStream, $output, $autocompleteHandlers, $state);
    }

    private function mostRecentlyEnteredValue(string $entered): string
    {
        // Determine the most recent value that the user entered
        if (!str_contains($entered, ',')) {
            return $entered;
        }

        if (false === $lastCommaPos = strrpos($entered, ',')) {
            return $entered;
        }

        $lastChoice = trim(substr($entered, $lastCommaPos + 1));

        return '' !== $lastChoice ? $lastChoice : $entered;
    }

    /**
     * Gets a hidden response from user.
     *
     * @param resource $inputStream The handler resource
     * @param bool     $trimmable   Is the answer trimmable
     *
     * @throws RuntimeException In case the fallback is deactivated and the response cannot be hidden
     */
    private function getHiddenResponse(OutputInterface $output, $inputStream, bool $trimmable = true): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR && $this->isInteractiveInput($inputStream)) {
            $exe = __DIR__.'/../Resources/bin/hiddeninput.exe';

            // handle code running from a phar
            if (str_starts_with(__FILE__, 'phar:')) {
                $tmpExe = sys_get_temp_dir().'/hiddeninput.exe';
                copy($exe, $tmpExe);
                $exe = $tmpExe;
            }

            $sExec = (string) shell_exec('"'.$exe.'"');
            $value = $trimmable ? rtrim($sExec) : $sExec;
            $output->writeln('');

            if (isset($tmpExe)) {
                unlink($tmpExe);
            }

            return $value;
        }

        $inputHelper = null;

        if (self::$stty && Terminal::hasSttyAvailable()) {
            $inputHelper = new TerminalInputHelper($inputStream);
            shell_exec('stty -echo');
        } elseif ($this->isInteractiveInput($inputStream)) {
            throw new RuntimeException('Unable to hide the response.');
        }

        $value = $this->doReadInput($inputStream, $output, helper: $inputHelper);

        if (4095 === \strlen($value)) {
            $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errOutput->warning('The value was possibly truncated by your shell or terminal emulator');
        }

        // Restore the terminal so it behaves normally again
        $inputHelper?->finish();

        if ($trimmable) {
            $value = trim($value);
        }
        $output->writeln('');

        return $value;
    }

    /**
     * Validates an attempt.
     *
     * @param callable $interviewer A callable that will ask for a question and return the result
     *
     * @param-immediately-invoked-callable $interviewer
     *
     * @throws \Exception In case the max number of attempts has been reached and no valid response has been given
     */
    private function validateAttempts(callable $interviewer, OutputInterface $output, Question $question): mixed
    {
        $error = null;
        $attempts = $question->getMaxAttempts();

        while (null === $attempts || $attempts--) {
            if (null !== $error) {
                $this->writeError($output, $error);
            }

            try {
                $value = $interviewer();

                if ($constraints = $question->getConstraints()) {
                    $this->validateConstraints($value, $constraints);
                }

                if ($validator = $question->getValidator()) {
                    return $validator($value);
                }

                return $value;
            } catch (MissingInputException $e) {
                throw $error ?? $e;
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Exception $error) {
            }
        }

        throw $error;
    }

    private function validateConstraints(mixed $value, array $constraints): void
    {
        if ($this->dispatcher) {
            $event = new QuestionAnsweredEvent($value, $constraints);
            $this->dispatcher->dispatch($event, ConsoleEvents::QUESTION_ANSWERED);

            if ($event->hasViolations()) {
                throw new InvalidArgumentException($event->getViolations()[0]);
            }

            return;
        }

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraints);

        if (\count($violations) > 0) {
            throw new InvalidArgumentException($violations[0]->getMessage());
        }
    }

    private function isInteractiveInput($inputStream): bool
    {
        if ('php://stdin' !== (stream_get_meta_data($inputStream)['uri'] ?? null)) {
            return false;
        }

        if (isset(self::$stdinIsInteractive)) {
            return self::$stdinIsInteractive;
        }

        return self::$stdinIsInteractive = @stream_isatty(fopen('php://stdin', 'r'));
    }

    /**
     * Reads one or more lines of input and returns what is read.
     *
     * @param resource $inputStream The handler resource
     * @param Question $question    The question being asked
     */
    private function readInput($inputStream, OutputInterface $output, Question $question): string|false
    {
        if (null !== $question->getTimeout() && $this->isInteractiveInput($inputStream)) {
            $read = [$inputStream];
            $write = null;
            $except = null;
            $timeoutSeconds = $question->getTimeout();
            $changedStreams = stream_select($read, $write, $except, $timeoutSeconds);

            if (0 === $changedStreams) {
                throw new MissingInputException(\sprintf('Timed out after waiting for input for %d second%s.', $timeoutSeconds, 1 === $timeoutSeconds ? '' : 's'));
            }
        }

        if (!$question->isMultiline()) {
            $cp = $this->setIOCodepage();
            $ret = $this->doReadInput($inputStream, $output);

            return $this->resetIOCodepage($cp, $ret);
        }

        $multiLineStreamReader = $this->cloneInputStream($inputStream);
        if (null === $multiLineStreamReader) {
            return false;
        }

        $cp = $this->setIOCodepage();
        $ret = $this->doReadInput($multiLineStreamReader, $output, "\x4");

        if (stream_get_meta_data($inputStream)['seekable']) {
            fseek($inputStream, ftell($multiLineStreamReader));
        }

        return $this->resetIOCodepage($cp, $ret);
    }

    private function setIOCodepage(): int
    {
        if (\function_exists('sapi_windows_cp_set')) {
            $cp = sapi_windows_cp_get();
            sapi_windows_cp_set(sapi_windows_cp_get('oem'));

            return $cp;
        }

        return 0;
    }

    /**
     * Sets console I/O to the specified code page and converts the user input.
     */
    private function resetIOCodepage(int $cp, string|false $input): string|false
    {
        if (0 !== $cp) {
            sapi_windows_cp_set($cp);

            if (false !== $input && '' !== $input) {
                $input = sapi_windows_cp_conv(sapi_windows_cp_get('oem'), $cp, $input);
            }
        }

        return $input;
    }

    /**
     * Clones an input stream in order to act on one instance of the same
     * stream without affecting the other instance.
     *
     * @param resource $inputStream The handler resource
     *
     * @return resource|null The cloned resource, null in case it could not be cloned
     */
    private function cloneInputStream($inputStream)
    {
        $streamMetaData = stream_get_meta_data($inputStream);
        $seekable = $streamMetaData['seekable'] ?? false;
        $mode = $streamMetaData['mode'] ?? 'rb';
        $uri = $streamMetaData['uri'] ?? null;

        if (null === $uri) {
            return null;
        }

        $cloneStream = fopen($uri, $mode);

        // For seekable and writable streams, add all the same data to the
        // cloned stream and then seek to the same offset.
        if (true === $seekable && !\in_array($mode, ['r', 'rb', 'rt'], true)) {
            $offset = ftell($inputStream);
            rewind($inputStream);
            stream_copy_to_stream($inputStream, $cloneStream);
            fseek($inputStream, $offset);
            fseek($cloneStream, $offset);
        }

        return $cloneStream;
    }

    /**
     * @param resource $inputStream
     */
    private function doReadInput($inputStream, OutputInterface $output, ?string $exitChar = null, ?TerminalInputHelper $helper = null): string
    {
        $customHandlers = [];

        // If the developer specified a custom exit character (like EOF),
        // we map that character to close the loop.
        if (null !== $exitChar) {
            $customHandlers[$exitChar] = static function ($state, $cursor, $output) {
                $state->done = true;
            };
        }

        // Add the \PHP_EOL edge case from the original code
        $customHandlers["\r"] = static function ($state, $cursor, $output) use ($exitChar) {
            if (null === $exitChar) {
                $output->write("\n");
                $state->done = true;
            }
        };

        // Call the engine we just built
        return $this->runInputEngine($inputStream, $output, $customHandlers);
    }
}
