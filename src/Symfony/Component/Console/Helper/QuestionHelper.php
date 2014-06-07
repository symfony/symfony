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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * The QuestionHelper class provides helpers to interact with the user.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class QuestionHelper extends Helper
{
    private $inputStream;
    private static $shell;
    private static $stty;

    /**
     * Asks a question to the user.
     *
     * @param InputInterface  $input    An InputInterface instance
     * @param OutputInterface $output   An OutputInterface instance
     * @param Question        $question The question to ask
     *
     * @return string The user answer
     *
     * @throws \RuntimeException If there is no data to read in the input stream
     */
    public function ask(InputInterface $input, OutputInterface $output, Question $question)
    {
        if (!$input->isInteractive()) {
            return $question->getDefault();
        }

        if (!$question->getValidator()) {
            return $this->doAsk($output, $question);
        }

        $that = $this;

        $interviewer = function () use ($output, $question, $that) {
            return $that->doAsk($output, $question);
        };

        return $this->validateAttempts($interviewer, $output, $question);
    }

    /**
     * Sets the input stream to read from when interacting with the user.
     *
     * This is mainly useful for testing purpose.
     *
     * @param resource $stream The input stream
     *
     * @throws \InvalidArgumentException In case the stream is not a resource
     */
    public function setInputStream($stream)
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Input stream must be a valid resource.');
        }

        $this->inputStream = $stream;
    }

    /**
     * Returns the helper's input stream
     *
     * @return resource
     */
    public function getInputStream()
    {
        return $this->inputStream;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'question';
    }

    /**
     * Asks the question to the user.
     *
     * This method is public for PHP 5.3 compatibility, it should be private.
     *
     * @param OutputInterface $output
     * @param Question        $question
     *
     * @return bool|mixed|null|string
     *
     * @throws \Exception
     * @throws \RuntimeException
     */
    public function doAsk(OutputInterface $output, Question $question)
    {
        $inputStream = $this->inputStream ?: STDIN;

        $message = $question->getQuestion();
        if ($question instanceof ChoiceQuestion) {
            $width = max(array_map('strlen', array_keys($question->getChoices())));

            $messages = (array) $question->getQuestion();
            foreach ($question->getChoices() as $key => $value) {
                $messages[] = sprintf("  [<info>%-${width}s</info>] %s", $key, $value);
            }

            $output->writeln($messages);

            $message = $question->getPrompt();
        }

        $output->write($message);

        if (!InputReader::isEachKeyPressModeAvailable()) {
            $answer = $this->processHidden($output, $question);

            if (false === $answer) {
                $answer = fgets($inputStream, 4096);
                if (false === $answer) {
                    throw new \RuntimeException('Aborted');
                }
            }
        } else {
            $autocomplete = $question->getAutocompleterValues();
            if ($autocomplete) {
                $output->getFormatter()->setStyle('hl', new OutputFormatterStyle('black', 'white'));
                $reader = new SuggestionInputReader($inputStream);
                $reader->setWraps('<hl>', '</hl>');
                $reader->setSuggestions($autocomplete);
                $answer = $reader->read($output);
            } else {
                $answer = $this->processHidden($output, $question, $inputStream);
                if (false === $answer) {
                    $reader = new InputReader($inputStream);
                    $answer = $reader->read($output);
                }
            }
        }

        $answer = trim($answer);
        $answer = strlen($answer) > 0 ? $answer : $question->getDefault();

        if ($normalizer = $question->getNormalizer()) {
            return $normalizer($answer);
        }

        return $answer;
    }

    /**
     * @param OutputInterface $output
     * @param Question $question
     * @param $inputStream
     * @throws \Exception
     * @throws \RuntimeException
     */
    private function processHidden(OutputInterface $output, Question $question, $inputStream)
    {
        $answer = false;
        if ($question->isHidden()) {
            try {
                $answer = $this->getHiddenResponse($output, $inputStream);
            } catch (\RuntimeException $e) {
                if (!$question->isHiddenFallback()) {
                    throw $e;
                }
            }
        }

        return $answer;
    }

    /**
     * Gets a hidden response from user.
     *
     * @param OutputInterface $output   An Output instance
     *
     * @return string         The answer
     *
     * @throws \RuntimeException In case the fallback is deactivated and the response cannot be hidden
     */
    private function getHiddenResponse(OutputInterface $output, $inputStream)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $exe = __DIR__.'/../Resources/bin/hiddeninput.exe';

            // handle code running from a phar
            if ('phar:' === substr(__FILE__, 0, 5)) {
                $tmpExe = sys_get_temp_dir().'/hiddeninput.exe';
                copy($exe, $tmpExe);
                $exe = $tmpExe;
            }

            $value = rtrim(shell_exec($exe));
            $output->writeln('');

            if (isset($tmpExe)) {
                unlink($tmpExe);
            }

            return $value;
        }

        if ($this->hasSttyAvailable()) {
            $sttyMode = shell_exec('stty -g');

            shell_exec('stty -echo');
            $value = fgets($inputStream, 4096);
            shell_exec(sprintf('stty %s', $sttyMode));

            if (false === $value) {
                throw new \RuntimeException('Aborted');
            }

            $value = trim($value);
            $output->writeln('');

            return $value;
        }

        if (false !== $shell = $this->getShell()) {
            $readCmd = $shell === 'csh' ? 'set mypassword = $<' : 'read -r mypassword';
            $command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);
            $value = rtrim(shell_exec($command));
            $output->writeln('');

            return $value;
        }

        throw new \RuntimeException('Unable to hide the response.');
    }

    /**
     * Validates an attempt.
     *
     * @param callable        $interviewer  A callable that will ask for a question and return the result
     * @param OutputInterface $output       An Output instance
     * @param Question        $question     A Question instance
     *
     * @return string   The validated response
     *
     * @throws \Exception In case the max number of attempts has been reached and no valid response has been given
     */
    private function validateAttempts($interviewer, OutputInterface $output, Question $question)
    {
        $error = null;
        $attempts = $question->getMaxAttempts();
        while (null === $attempts || $attempts--) {
            if (null !== $error) {
                $output->writeln($this->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
            }

            try {
                return call_user_func($question->getValidator(), $interviewer());
            } catch (\Exception $error) {
            }
        }

        throw $error;
    }

    /**
     * Returns a valid unix shell.
     *
     * @return string|bool     The valid shell name, false in case no valid shell is found
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
            foreach (array('bash', 'zsh', 'ksh', 'csh') as $sh) {
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
     *
     * @return bool
     */
    private function hasSttyAvailable()
    {
        if (null !== self::$stty) {
            return self::$stty;
        }

        exec('stty 2>&1', $output, $exitcode);

        return self::$stty = $exitcode === 0;
    }
}
