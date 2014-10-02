<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Style\Standard;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\AbstractOutputStyle;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class StandardOutputStyle extends AbstractOutputStyle
{
    /**
     * Formats a message as a block of text.
     *
     * @param string|array $messages  The message to write in the block
     * @param string|null  $type      The block type (added in [] on first line)
     * @param string|null  $style     The style to apply to the whole block
     * @param string       $prefix    The prefix for the block
     */
    public function block($messages, $type = null, $style = null, $prefix = ' ')
    {
        $this->format(new BlockFormatter($messages, $type, $style, $prefix));
    }

    /**
     * Formats a command title
     *
     * @param string $message
     */
    public function title($message)
    {
        $this->format(new TitleFormatter($message, '=', true));
    }

    /**
     * Formats a section title
     *
     * @param string $message
     */
    public function section($message)
    {
        $this->format(new TitleFormatter($message, '-'));
    }

    /**
     * Formats a list
     *
     * @param array $elements
     */
    public function listing(array $elements)
    {
        $this->format(new ListFormatter($elements));
    }

    /**
     * Formats informational text
     *
     * @param string|array $messages
     */
    public function text($messages)
    {
        $this->format(new TextFormatter($messages));
    }

    /**
     * Formats a success result bar
     *
     * @param string|array $messages
     */
    public function success($messages)
    {
        $this->format(new BlockFormatter($messages, 'OK', 'fg=white;bg=green'));
    }

    /**
     * Formats an error result bar
     *
     * @param string|array $messages
     */
    public function error($messages)
    {
        $this->format(new BlockFormatter($messages, 'ERROR', 'fg=white;bg=red'));
    }

    /**
     * Formats an warning result bar
     *
     * @param string|array $messages
     */
    public function warning($messages)
    {
        $this->format(new BlockFormatter($messages, 'WARNING', 'fg=black;bg=yellow'));
    }

    /**
     * Formats a note admonition
     *
     * @param string|array $messages
     */
    public function note($messages)
    {
        $this->format(new BlockFormatter($messages, 'NOTE', null, ' ! '));
    }

    /**
     * Formats a caution admonition
     *
     * @param string|array $messages
     */
    public function caution($messages)
    {
        $this->format(new BlockFormatter($messages, 'CAUTION', 'fg=white;bg=red', ' ! '));
    }

    /**
     * Formats a table
     *
     * @param array $headers
     * @param array $rows
     */
    public function table(array $headers, array $rows)
    {
        $table = new Table($this);
        $table->setHeaders($headers);
        $table->setRows($rows);

        $table->render();
        $this->ln();
    }

    /**
     * Asks a question
     *
     * @param Question|string $question
     * @param string|null     $default
     * @param callable|null   $validator
     *
     * @return string
     */
    public function ask($question, $default = null, $validator = null)
    {
        $question = new Question($question, $default);
        $question->setValidator($validator);

        return $this->askQuestion($question, $validator);
    }

    /**
     * Asks for confirmation
     *
     * @param string $question
     * @param bool   $default
     *
     * @return bool
     */
    public function confirm($question, $default = true)
    {
        return $this->askQuestion(new ConfirmationQuestion($question, $default));
    }

    /**
     * Asks a choice question
     *
     * @param string          $question
     * @param array           $choices
     * @param string|int|null $default
     *
     * @return string
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
     * @param Question $question
     *
     * @return string
     */
    public function askQuestion(Question $question)
    {
        $text = $question->getQuestion();
        $default = $question->getDefault();

        switch (true) {
            case null === $default:
                $text = sprintf(' <info>%s</info>:', $text);

                break;

            case $question instanceof ConfirmationQuestion:
                $text = sprintf(' <info>%s (yes/no)</info> [<comment>%s</comment>]:', $text, $default ? 'yes' : 'no');

                break;

            case $question instanceof ChoiceQuestion:
                $choices = $question->getChoices();
                $text = sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, $choices[$default]);

                break;

            default:
                $text = sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, $default);
        }

        $validator = $question->getValidator();
        $question->setValidator(function ($value) use ($validator) {
                if (null !== $validator && is_callable($validator)) {
                    $value = $validator($value);
                }

                // make required
                if (!is_bool($value) && 0 === strlen($value)) {
                    throw new \Exception('A value is required.');
                }

                return $value;
            });

        $ret = null;

        while (null === $ret) {
            $this->writeln($text);

            if ($question instanceof ChoiceQuestion) {
                $width = max(array_map('strlen', array_keys($question->getChoices())));

                foreach ($question->getChoices() as $key => $value) {
                    $this->writeln(sprintf("  [<comment>%-${width}s</comment>] %s", $key, $value));
                }
            }

            $this->write(' > ');

            $input = trim(fgets(STDIN, 4096));
            $input = strlen($input) > 0 ? $input : $default;

            try {
                $ret = call_user_func($question->getValidator(), $input);
            } catch (\Exception $e) {
                $this->ln();
                $this->error($e->getMessage());
            }
        }

        $this->ln();

        if ($normalizer = $question->getNormalizer()) {
            return $normalizer($ret);
        }

        return $ret;
    }
}

