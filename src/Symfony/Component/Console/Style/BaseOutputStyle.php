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

use Symfony\Component\Console\Block\MessageBlock;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\TrimmedBufferOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Terminal;

/**
 * Base output style methods implementation.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 * @author Vadim Zharkov <hushker@gmail.com>
 */
class BaseOutputStyle extends OutputStyle
{
    public const MAX_LINE_LENGTH = 120;
    protected const TITLE_CHAR = '=';
    protected const SECTION_CHAR = '-';
    protected const LISTING_CHAR = '*';
    protected const COMMENT_STYLE = '<fg=default;bg=default> // </>';
    protected const SUCCESS_STYLE = 'fg=black;bg=green';
    protected const ERROR_STYLE = 'fg=white;bg=red';
    protected const WARNING_STYLE = 'fg=black;bg=yellow';
    protected const NOTE_STYLE = 'fg=yellow';
    protected const INFO_STYLE = 'fg=green';

    protected $input;
    protected $questionHelper;
    protected $progressBar;
    protected $lineLength;
    protected $bufferedOutput;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->bufferedOutput = new TrimmedBufferOutput(\DIRECTORY_SEPARATOR === '\\' ? 4 : 2, $output->getVerbosity(), false, clone $output->getFormatter());
        // Windows cmd wraps lines as soon as the terminal width is reached, whether there are following chars or not.
        $width = (new Terminal())->getWidth() ?: self::MAX_LINE_LENGTH;
        $this->lineLength = min($width - (int) (\DIRECTORY_SEPARATOR === '\\'), self::MAX_LINE_LENGTH);

        parent::__construct($output);
    }

    /**
     * Formats a message as a block of text.
     *
     * @param string|array $messages The message to write in the block
     */
    public function block($messages, string $type = null, string $style = null, string $prefix = ' ', bool $padding = false, bool $escape = true)
    {
        $messages = \is_array($messages) ? array_values($messages) : [$messages];

        $this->autoPrependBlock();
        $this->writeln($this->createBlock($messages, $type, $style, $prefix, $padding, $escape));
        $this->newLine();
    }

    /**
     * {@inheritdoc}
     */
    public function title(string $message)
    {
        $this->autoPrependBlock();
        $this->writeln([
            sprintf('<comment>%s</>', OutputFormatter::escapeTrailingBackslash($message)),
            sprintf('<comment>%s</>', str_repeat(static::TITLE_CHAR, Helper::width(Helper::removeDecoration($this->getFormatter(), $message)))),
        ]);
        $this->newLine();
    }

    /**
     * {@inheritdoc}
     */
    public function section(string $message)
    {
        $this->autoPrependBlock();
        $this->writeln([
            sprintf('<comment>%s</>', OutputFormatter::escapeTrailingBackslash($message)),
            sprintf('<comment>%s</>', str_repeat(static::SECTION_CHAR, Helper::width(Helper::removeDecoration($this->getFormatter(), $message)))),
        ]);
        $this->newLine();
    }

    /**
     * {@inheritdoc}
     */
    public function listing(array $elements)
    {
        $this->autoPrependText();
        $elements = array_map(function ($element) {
            return sprintf(' %s %s', static::LISTING_CHAR, $element);
        }, $elements);

        $this->writeln($elements);
        $this->newLine();
    }

    /**
     * {@inheritdoc}
     */
    public function text($message)
    {
        $this->autoPrependText();

        $messages = \is_array($message) ? array_values($message) : [$message];
        foreach ($messages as $message) {
            $this->writeln(sprintf(' %s', $message));
        }
    }

    /**
     * Formats a command comment.
     *
     * @param string|array $message
     */
    public function comment($message)
    {
        $this->block($message, null, null, static::COMMENT_STYLE, false, false);
    }

    /**
     * {@inheritdoc}
     */
    public function success($message)
    {
        $this->block($message, 'OK', static::SUCCESS_STYLE, ' ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function error($message)
    {
        $this->block($message, 'ERROR', static::ERROR_STYLE, ' ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message)
    {
        $this->block($message, 'WARNING', static::WARNING_STYLE, ' ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function note($message)
    {
        $this->block($message, 'NOTE', static::NOTE_STYLE, ' ! ');
    }

    /**
     * Formats an info message.
     *
     * @param string|array $message
     */
    public function info($message)
    {
        $this->block($message, 'INFO', static::INFO_STYLE, ' ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function caution($message)
    {
        $this->block($message, 'CAUTION', static::ERROR_STYLE, ' ! ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function table(array $headers, array $rows)
    {
        $style = clone Table::getStyleDefinition('symfony-style-guide');
        $style->setCellHeaderFormat('<info>%s</info>');

        $table = new Table($this);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->setStyle($style);

        $table->render();
        $this->newLine();
    }

    /**
     * Formats a horizontal table.
     */
    public function horizontalTable(array $headers, array $rows)
    {
        $style = clone Table::getStyleDefinition('symfony-style-guide');
        $style->setCellHeaderFormat('<info>%s</info>');

        $table = new Table($this);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->setStyle($style);
        $table->setHorizontal(true);

        $table->render();
        $this->newLine();
    }

    /**
     * Formats a list of key/value horizontally.
     *
     * Each row can be one of:
     * * 'A title'
     * * ['key' => 'value']
     * * new TableSeparator()
     *
     * @param string|array|TableSeparator ...$list
     */
    public function definitionList(...$list)
    {
        $style = clone Table::getStyleDefinition('symfony-style-guide');
        $style->setCellHeaderFormat('<info>%s</info>');

        $table = new Table($this);
        $headers = [];
        $row = [];
        foreach ($list as $value) {
            if ($value instanceof TableSeparator) {
                $headers[] = $value;
                $row[] = $value;
                continue;
            }
            if (\is_string($value)) {
                $headers[] = new TableCell($value, ['colspan' => 2]);
                $row[] = null;
                continue;
            }
            if (!\is_array($value)) {
                throw new InvalidArgumentException('Value should be an array, string, or an instance of TableSeparator.');
            }
            $headers[] = key($value);
            $row[] = current($value);
        }

        $table->setHeaders($headers);
        $table->setRows([$row]);
        $table->setHorizontal();
        $table->setStyle($style);

        $table->render();
        $this->newLine();
    }

    /**
     * {@inheritdoc}
     */
    public function ask(string $question, string $default = null, callable $validator = null)
    {
        $question = new Question($question, $default);
        $question->setValidator($validator);

        return $this->askQuestion($question);
    }

    /**
     * {@inheritdoc}
     */
    public function askHidden(string $question, callable $validator = null)
    {
        $question = new Question($question);

        $question->setHidden(true);
        $question->setValidator($validator);

        return $this->askQuestion($question);
    }

    /**
     * {@inheritdoc}
     */
    public function confirm(string $question, bool $default = true)
    {
        return $this->askQuestion(new ConfirmationQuestion($question, $default));
    }

    /**
     * {@inheritdoc}
     */
    public function choice(string $question, array $choices, $default = null)
    {
        if (null !== $default) {
            $values = array_flip($choices);
            $default = $values[$default] ?? $default;
        }

        return $this->askQuestion(new ChoiceQuestion($question, $choices, $default));
    }

    /**
     * {@inheritdoc}
     */
    public function progressStart(int $max = 0)
    {
        $this->progressBar = $this->createProgressBar($max);
        $this->progressBar->start();
    }

    /**
     * {@inheritdoc}
     */
    public function progressAdvance(int $step = 1)
    {
        $this->getProgressBar()->advance($step);
    }

    /**
     * {@inheritdoc}
     */
    public function progressFinish()
    {
        $this->getProgressBar()->finish();
        $this->newLine(2);
        $this->progressBar = null;
    }

    /**
     * {@inheritdoc}
     */
    public function createProgressBar(int $max = 0)
    {
        $progressBar = parent::createProgressBar($max);

        if ('\\' !== \DIRECTORY_SEPARATOR || 'Hyper' === getenv('TERM_PROGRAM')) {
            $progressBar->setEmptyBarCharacter('░'); // light shade character \u2591
            $progressBar->setProgressCharacter('');
            $progressBar->setBarCharacter('▓'); // dark shade character \u2593
        }

        return $progressBar;
    }

    /**
     * @return mixed
     */
    public function askQuestion(Question $question)
    {
        if ($this->input->isInteractive()) {
            $this->autoPrependBlock();
        }

        if (!$this->questionHelper) {
            $this->questionHelper = new SymfonyQuestionHelper();
        }

        $answer = $this->questionHelper->ask($this->input, $this, $question);

        if ($this->input->isInteractive()) {
            $this->newLine();
            $this->bufferedOutput->write("\n");
        }

        return $answer;
    }

    /**
     * {@inheritdoc}
     */
    public function writeln($messages, int $options = self::OUTPUT_NORMAL)
    {
        if (!is_iterable($messages)) {
            $messages = [$messages];
        }

        foreach ($messages as $message) {
            parent::writeln($message, $options);
            $this->writeBuffer($message, true, $options);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($messages, bool $newline = false, int $options = self::OUTPUT_NORMAL)
    {
        if (!is_iterable($messages)) {
            $messages = [$messages];
        }

        foreach ($messages as $message) {
            parent::write($message, $newline, $options);
            $this->writeBuffer($message, $newline, $options);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function newLine(int $count = 1)
    {
        parent::newLine($count);
        $this->bufferedOutput->write(str_repeat("\n", $count));
    }

    /**
     * Returns a new instance which makes use of stderr if available.
     *
     * @return self
     */
    public function getErrorStyle()
    {
        return new static($this->input, $this->getErrorOutput());
    }

    protected function getProgressBar(): ProgressBar
    {
        if (!$this->progressBar) {
            throw new RuntimeException('The ProgressBar is not started.');
        }

        return $this->progressBar;
    }

    protected function autoPrependBlock(): void
    {
        $chars = substr(str_replace(\PHP_EOL, "\n", $this->bufferedOutput->fetch()), -2);

        if (!isset($chars[0])) {
            $this->newLine(); //empty history, so we should start with a new line.

            return;
        }
        //Prepend new line for each non LF chars (This means no blank line was output before)
        $this->newLine(2 - substr_count($chars, "\n"));
    }

    protected function autoPrependText(): void
    {
        $fetched = $this->bufferedOutput->fetch();
        //Prepend new line if last char isn't EOL:
        if ("\n" !== substr($fetched, -1)) {
            $this->newLine();
        }
    }

    protected function writeBuffer(string $message, bool $newLine, int $type): void
    {
        // We need to know if the last chars are PHP_EOL
        $this->bufferedOutput->write($message, $newLine, $type);
    }

    protected function createBlock(array $messages, string $type = null, string $style = null, string $prefix = ' ', bool $padding = false, bool $escape = false): array
    {
        return MessageBlock::createBlock($messages, $this->getFormatter(), $type, $style, $prefix, $padding, $escape, $this->lineLength, $this->isDecorated());
    }
}
