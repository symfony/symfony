<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Style;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Output decorator helpers for the Symfony Style Guide.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class SymfonyStyle extends OutputStyle
{
    const MAX_LINE_LENGTH = 120;

    private $input;
    private $questionHelper;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        parent::__construct($output);
    }

    /**
     * Formats a message as a block of text.
     *
     * @param string|array $messages  The message to write in the block
     * @param string|null  $type      The block type (added in [] on first line)
     * @param string|null  $style     The style to apply to the whole block
     * @param string       $prefix    The prefix for the block
     * @param bool         $padding   Whether to add vertical padding
     */
    public function block($messages, $type = null, $style = null, $prefix = ' ', $padding = false)
    {
        $messages = array_values((array) $messages);
        $lines = array();

        // add type
        if (null !== $type) {
            $messages[0] = sprintf('[%s] %s', $type, $messages[0]);
        }

        // wrap and add newlines for each element
        foreach ($messages as $key => $message) {
            $message = OutputFormatter::escape($message);
            $lines = array_merge($lines, explode("\n", wordwrap($message, self::MAX_LINE_LENGTH - Helper::strlen($prefix))));

            if (count($messages) > 1 && $key < count($message)) {
                $lines[] = '';
            }
        }

        if ($padding && $this->isDecorated()) {
            array_unshift($lines, '');
            $lines[] = '';
        }

        foreach ($lines as &$line) {
            $line = sprintf('%s%s', $prefix, $line);
            $line .= str_repeat(' ', self::MAX_LINE_LENGTH - Helper::strlen($line));

            if ($style) {
                $line = sprintf('<%s>%s</>', $style, $line);
            }
        }

        $this->writeln(implode("\n", $lines)."\n");
    }

    /**
     * {@inheritdoc}
     */
    public function title($message)
    {
        $this->writeln(sprintf("\n<fg=blue>%s</fg=blue>\n<fg=blue>%s</fg=blue>\n", $message, str_repeat('=', strlen($message))));
    }

    /**
     * {@inheritdoc}
     */
    public function section($message)
    {
        $this->writeln(sprintf("<fg=blue>%s</fg=blue>\n<fg=blue>%s</fg=blue>\n", $message, str_repeat('-', strlen($message))));
    }

    /**
     * {@inheritdoc}
     */
    public function listing(array $elements)
    {
        $elements = array_map(function ($element) {
                return sprintf(' * %s', $element);
            },
            $elements
        );

        $this->writeln(implode("\n\n", $elements)."\n");
    }

    /**
     * {@inheritdoc}
     */
    public function text($message)
    {
        if (!is_array($message)) {
            $this->writeln(sprintf(' // %s', $message));

            return;
        }

        foreach ($message as $element) {
            $this->text($element);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function success($message)
    {
        $this->block($message, 'OK', 'fg=white;bg=green', ' ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function error($message)
    {
        $this->block($message, 'ERROR', 'fg=white;bg=red', ' ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message)
    {
        $this->block($message, 'WARNING', 'fg=white;bg=red', ' ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function note($message)
    {
        $this->block($message, 'NOTE', 'fg=yellow', ' ! ');
    }

    /**
     * {@inheritdoc}
     */
    public function caution($message)
    {
        $this->block($message, 'CAUTION', 'fg=white;bg=red', ' ! ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function table(array $headers, array $rows)
    {
        $table = new Table($this);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->setStyle('symfony-style-guide');

        $table->render();
        $this->ln();
    }

    /**
     * {@inheritdoc}
     */
    public function ask($question, $default = null, $validator = null)
    {
        $question = new Question($question, $default);
        $question->setValidator($validator);

        return $this->askQuestion($question, $validator);
    }

    /**
     * {@inheritdoc}
     */
    public function askHidden($question, $validator = null)
    {
        $question = new Question($question);
        $question->setHidden(true);

        return $this->askQuestion($question, $validator);
    }

    /**
     * {@inheritdoc}
     */
    public function confirm($question, $default = true)
    {
        return $this->askQuestion(new ConfirmationQuestion($question, $default));
    }

    /**
     * {@inheritdoc}
     */
    public function choice($question, array $choices, $default = null)
    {
        if (null !== $default) {
            $values = array_flip($choices);
            $default = $values[$default];
        }

        return $this->askQuestion(new ChoiceQuestion($question, $choices, $default));
    }

    /**
     * {@inheritdoc}
     */
    public function multipleChoice($question, array $choices, array $default = array())
    {
        $question = new ChoiceQuestion($question, $choices, $default);
        $question->setMultiselect(true);

        return $this->askQuestion($question);
    }

    /**
     * @param Question $question
     *
     * @return string
     */
    public function askQuestion(Question $question)
    {
        if (!$this->questionHelper) {
            $this->questionHelper = new SymfonyQuestionHelper();
        }

        $answer = $this->questionHelper->ask($this->input, $this, $question);

        $this->ln();

        return $answer;
    }
}
