<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\Formatter\BlockFormatter;
use Symfony\Component\Console\Helper\Formatter\FormatterInterface;
use Symfony\Component\Console\Helper\Formatter\ListElementFormatter;
use Symfony\Component\Console\Helper\Formatter\SectionFormatter;
use Symfony\Component\Console\Helper\Formatter\SectionTitleFormatter;
use Symfony\Component\Console\Helper\Formatter\StyledBlockFormatter;
use Symfony\Component\Console\Helper\Formatter\TextFormatter;
use Symfony\Component\Console\Helper\Formatter\TitleFormatter;

/**
 * Decorates output to add console style guide helper methods
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class OutputDecorator implements OutputInterface
{
    private $output;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param FormatterInterface $formatter
     */
    public function format(FormatterInterface $formatter)
    {
        $this->writeln($formatter->format());
    }

    /**
     * Formats a message within a section.
     *
     * @param string $section The section name
     * @param string $message The message
     * @param string $style   The style to apply to the section
     */
    public function section($section, $message, $style = 'info')
    {
        $this->format(new SectionFormatter($section, $message, $style));
    }

    /**
     * Formats a message as a block of text.
     *
     * @param string|array $messages  The message to write in the block
     * @param string       $style     The style to apply to the whole block
     * @param bool         $large     Whether to return a large block
     * @param int          $padLength Length to pad the messages
     */
    public function block($messages, $style, $large = false, $padLength = 0)
    {
        $this->format(new BlockFormatter($messages, $style, $large, $padLength));
    }

    /**
     * Formats a command title
     *
     * @param string $message
     */
    public function title($message)
    {
        $this->format(new TitleFormatter($message));
    }

    /**
     * Formats a section title
     *
     * @param string $message
     */
    public function subtitle($message)
    {
        $this->format(new SectionTitleFormatter($message));
    }

    /**
     * Formats a list element
     *
     * @param string|array $messages
     */
    public function listElement($messages)
    {
        $this->format(new ListElementFormatter($messages));
    }

    /**
     * Formats informational or debug text
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
        $this->format(new StyledBlockFormatter($messages, 'OK', 'fg=white;bg=green'));
    }

    /**
     * Formats an error result bar
     *
     * @param string|array $messages
     */
    public function error($messages)
    {
        $this->format(new StyledBlockFormatter($messages, 'ERROR', 'fg=white;bg=red'));
    }

    /**
     * Formats an warning result bar
     *
     * @param string|array $messages
     */
    public function warning($messages)
    {
        $this->format(new StyledBlockFormatter($messages, 'WARNING', 'fg=black;bg=yellow'));
    }

    /**
     * Formats a note admonition
     *
     * @param string|array $messages
     */
    public function note($messages)
    {
        $this->format(new StyledBlockFormatter($messages, 'NOTE', 'fg=white', '! '));
    }

    /**
     * Formats a caution admonition
     *
     * @param string|array $messages
     */
    public function caution($messages)
    {
        $this->format(new StyledBlockFormatter($messages, 'CAUTION', 'fg=white;bg=red', '! '));
    }

    /**
     * {@inheritdoc}
     */
    public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL)
    {
        $this->output->write($messages, $newline, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function writeln($messages, $type = self::OUTPUT_NORMAL)
    {
        $this->output->writeln($messages, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function setVerbosity($level)
    {
        $this->output->setVerbosity($level);
    }

    /**
     * {@inheritdoc}
     */
    public function getVerbosity()
    {
        return $this->output->getVerbosity();
    }

    /**
     * {@inheritdoc}
     */
    public function setDecorated($decorated)
    {
        $this->output->setDecorated($decorated);
    }

    /**
     * {@inheritdoc}
     */
    public function isDecorated()
    {
        return $this->output->isDecorated();
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(OutputFormatterInterface $formatter)
    {
        $this->output->setFormatter($formatter);
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter()
    {
        return $this->output->getFormatter();
    }
}
