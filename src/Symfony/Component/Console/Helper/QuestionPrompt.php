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

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Provides helpers to interact with the user.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class QuestionPrompt
{
    protected $input;
    protected $output;
    protected $question;
    protected $inputStream;
    protected $formatter;
    private static $shell;
    private static $stty;

    public function __construct(InputInterface $input, OutputInterface $output, Question $question)
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        $this->input = $input;
        $this->output = $output;
        $this->question = $question;
        $this->inputStream = STDIN;
        $this->formatter = new Formatter();

        if ($this->input instanceof StreamableInputInterface && $stream = $this->input->getStream()) {
            $this->inputStream = $stream;
        }
    }

    /**
     * Prevents usage of stty.
     */
    public static function disableStty()
    {
        self::$stty = false;
    }

    public function setFormatter(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }

    public function ask()
    {
        if (!$this->input->isInteractive()) {
            $default = $this->question->getDefault();

            if (null === $default) {
                return $default;
            }

            if ($validator = $this->question->getValidator()) {
                return \call_user_func($this->question->getValidator(), $default);
            } elseif ($this->question instanceof ChoiceQuestion) {
                $choices = $this->question->getChoices();

                if (!$this->question->isMultiselect()) {
                    return isset($choices[$default]) ? $choices[$default] : $default;
                }

                $default = explode(',', $default);
                foreach ($default as $k => $v) {
                    $v = trim($v);
                    $default[$k] = isset($choices[$v]) ? $choices[$v] : $v;
                }
            }

            return $default;
        }

        if (!$this->question->getValidator()) {
            return $this->doAsk();
        }

        $interviewer = function () {
            return $this->doAsk();
        };

        return $this->validateAttempts($interviewer);
    }

    private function doAsk()
    {
        $this->writePrompt();

        $inputStream = $this->inputStream ?: STDIN;
        $autocomplete = $this->question->getAutocompleterCallback();

        if (null === $autocomplete || !$this->hasSttyAvailable()) {
            $ret = false;
            if ($this->question->isHidden()) {
                try {
                    $ret = trim($this->getHiddenResponse());
                } catch (RuntimeException $e) {
                    if (!$this->question->isHiddenFallback()) {
                        throw $e;
                    }
                }
            }

            if (false === $ret) {
                $ret = fgets($inputStream, 4096);
                if (false === $ret) {
                    throw new RuntimeException('Aborted.');
                }
                $ret = trim($ret);
            }
        } else {
            $ret = trim($this->autocomplete($autocomplete));
        }

        if ($this->output instanceof ConsoleSectionOutput) {
            $this->output->addContent($ret);
        }

        $ret = \strlen($ret) > 0 ? $ret : $this->question->getDefault();

        if ($normalizer = $this->question->getNormalizer()) {
            return $normalizer($ret);
        }

        return $ret;
    }

    /**
     * Outputs the question prompt.
     */
    protected function writePrompt()
    {
        $message = $this->question->getQuestion();

        if ($this->question instanceof ChoiceQuestion) {
            $maxWidth = max(array_map([$this->formatter, 'strlen'], array_keys($this->question->getChoices())));

            $messages = (array) $this->question->getQuestion();
            foreach ($this->question->getChoices() as $key => $value) {
                $width = $maxWidth - Helper::strlen($key);
                $messages[] = '  [<info>'.$key.str_repeat(' ', $width).'</info>] '.$value;
            }

            $this->output->writeln($messages);

            $message = $this->question->getPrompt();
        }

        $this->output->write($message);
    }

    /**
     * Outputs an error message.
     */
    protected function writeError(\Exception $error)
    {
        $message = $this->formatter->formatBlock($error->getMessage(), 'error');

        $this->output->writeln($message);
    }

    /**
     * Autocompletes a question.
     */
    private function autocomplete(callable $autocomplete): string
    {
        $ret = '';

        $i = 0;
        $ofs = -1;
        $matches = $autocomplete($ret);
        $numMatches = \count($matches);

        $sttyMode = shell_exec('stty -g');

        // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
        shell_exec('stty -icanon -echo');

        // Add highlighted text style
        $this->output->getFormatter()->setStyle('hl', new OutputFormatterStyle('black', 'white'));

        // Read a keypress
        while (!feof($this->inputStream)) {
            $c = fread($this->inputStream, 1);

            // as opposed to fgets(), fread() returns an empty string when the stream content is empty, not false.
            if (false === $c || ('' === $ret && '' === $c && null === $this->question->getDefault())) {
                shell_exec(sprintf('stty %s', $sttyMode));
                throw new RuntimeException('Aborted.');
            } elseif ("\177" === $c) { // Backspace Character
                if (0 === $numMatches && 0 !== $i) {
                    --$i;
                    // Move cursor backwards
                    $this->output->write("\033[1D");
                }

                if (0 === $i) {
                    $ofs = -1;
                    $matches = $autocomplete($ret);
                    $numMatches = \count($matches);
                } else {
                    $numMatches = 0;
                }

                // Pop the last character off the end of our string
                $ret = substr($ret, 0, $i);
            } elseif ("\033" === $c) {
                // Did we read an escape sequence?
                $c .= fread($this->inputStream, 2);

                // A = Up Arrow. B = Down Arrow
                if (isset($c[2]) && ('A' === $c[2] || 'B' === $c[2])) {
                    if ('A' === $c[2] && -1 === $ofs) {
                        $ofs = 0;
                    }

                    if (0 === $numMatches) {
                        continue;
                    }

                    $ofs += ('A' === $c[2]) ? -1 : 1;
                    $ofs = ($numMatches + $ofs) % $numMatches;
                }
            } elseif (\ord($c) < 32) {
                if ("\t" === $c || "\n" === $c) {
                    if ($numMatches > 0 && -1 !== $ofs) {
                        $ret = (string) $matches[$ofs];
                        // Echo out remaining chars for current match
                        $this->output->write(substr($ret, $i));
                        $i = \strlen($ret);

                        $matches = array_filter(
                            $autocomplete($ret),
                            function ($match) use ($ret) {
                                return '' === $ret || 0 === strpos($match, $ret);
                            }
                        );
                        $numMatches = \count($matches);
                        $ofs = -1;
                    }

                    if ("\n" === $c) {
                        $this->output->write($c);
                        break;
                    }
                }

                continue;
            } else {
                if ("\x80" <= $c) {
                    $c .= fread($this->inputStream, ["\xC0" => 1, "\xD0" => 1, "\xE0" => 2, "\xF0" => 3][$c & "\xF0"]);
                }

                $this->output->write($c);
                $ret .= $c;
                ++$i;

                $numMatches = 0;
                $ofs = 0;

                foreach ($autocomplete($ret) as $value) {
                    // If typed characters match the beginning chunk of value (e.g. [AcmeDe]moBundle)
                    if (0 === strpos($value, $ret)) {
                        $matches[$numMatches++] = $value;
                    }
                }
            }

            // Erase characters from cursor to end of line
            $this->output->write("\033[K");

            if ($numMatches > 0 && -1 !== $ofs) {
                // Save cursor position
                $this->output->write("\0337");
                // Write highlighted text
                $this->output->write('<hl>'.OutputFormatter::escapeTrailingBackslash(substr($matches[$ofs], $i)).'</hl>');
                // Restore cursor position
                $this->output->write("\0338");
            }
        }

        // Reset stty so it behaves normally again
        shell_exec(sprintf('stty %s', $sttyMode));

        return $ret;
    }

    /**
     * Gets a hidden response from user.
     *
     * @throws RuntimeException In case the fallback is deactivated and the response cannot be hidden
     */
    private function getHiddenResponse(): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $exe = __DIR__.'/../Resources/bin/hiddeninput.exe';

            // handle code running from a phar
            if ('phar:' === substr(__FILE__, 0, 5)) {
                $tmpExe = sys_get_temp_dir().'/hiddeninput.exe';
                copy($exe, $tmpExe);
                $exe = $tmpExe;
            }

            $value = rtrim(shell_exec($exe));
            $this->output->writeln('');

            if (isset($tmpExe)) {
                unlink($tmpExe);
            }

            return $value;
        }

        if ($this->hasSttyAvailable()) {
            $sttyMode = shell_exec('stty -g');

            shell_exec('stty -echo');
            $value = fgets($this->inputStream, 4096);
            shell_exec(sprintf('stty %s', $sttyMode));

            if (false === $value) {
                throw new RuntimeException('Aborted.');
            }

            $value = trim($value);
            $this->output->writeln('');

            return $value;
        }

        if (false !== $shell = $this->getShell()) {
            $readCmd = 'csh' === $shell ? 'set mypassword = $<' : 'read -r mypassword';
            $command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);
            $value = rtrim(shell_exec($command));
            $this->output->writeln('');

            return $value;
        }

        throw new RuntimeException('Unable to hide the response.');
    }

    /**
     * Validates an attempt.
     *
     * @param callable $interviewer A callable that will ask for a question and return the result
     *
     * @return mixed The validated response
     *
     * @throws \Exception In case the max number of attempts has been reached and no valid response has been given
     */
    private function validateAttempts(callable $interviewer)
    {
        $error = null;
        $attempts = $this->question->getMaxAttempts();
        while (null === $attempts || $attempts--) {
            if (null !== $error) {
                $this->writeError($error);
            }

            try {
                return $this->question->getValidator()($interviewer());
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Exception $error) {
            }
        }

        throw $error;
    }

    /**
     * Returns a valid unix shell.
     *
     * @return string|bool The valid shell name, false in case no valid shell is found
     */
    private function getShell()
    {
        if (null !== self::$shell) {
            return self::$shell;
        }

        self::$shell = false;

        if (file_exists('/usr/bin/env')) {
            // handle other OSs with bash/zsh/ksh/csh if available to hide the answer
            $test = "/usr/bin/env %s -c 'echo OK' 2> /dev/null";
            foreach (['bash', 'zsh', 'ksh', 'csh'] as $sh) {
                if ('OK' === rtrim(shell_exec(sprintf($test, $sh)))) {
                    self::$shell = $sh;
                    break;
                }
            }
        }

        return self::$shell;
    }

    /**
     * Returns whether Stty is available or not.
     */
    private function hasSttyAvailable(): bool
    {
        if (null !== self::$stty) {
            return self::$stty;
        }

        exec('stty 2>&1', $output, $exitcode);

        return self::$stty = 0 === $exitcode;
    }
}
