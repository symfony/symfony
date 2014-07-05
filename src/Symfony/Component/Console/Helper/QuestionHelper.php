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
use Symfony\Component\Console\Question\GenericQuestionAsker;
use Symfony\Component\Console\Question\MultiChoiceQuestionAsker;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\SingleChoiceQuestionAsker;

/**
 * The QuestionHelper class provides helpers to interact with the user.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class QuestionHelper extends Helper
{
    private $inputStream;
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

        if ($question instanceof ChoiceQuestion && $this->hasSttyAvailable()) {
            if ($question->isMultiselect()) {
                $asker = new MultiChoiceQuestionAsker($question);
            } else {
                $asker = new SingleChoiceQuestionAsker($question);
            }
        } else {
            $asker = new GenericQuestionAsker($question, $this->hasSttyAvailable());
        }

        $inputStream = $this->inputStream ?: STDIN;

        $interviewer = function () use ($output, $question, $asker, $inputStream) {
            $response = $asker->ask($output, $inputStream);
            $response = strlen($response) > 0 ? $response : $question->getDefault();

            if ($normalizer = $question->getNormalizer()) {
                return $normalizer($response);
            }

            return $response;
        };

        if (!$question->getValidator()) {
            return $interviewer();
        }

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
