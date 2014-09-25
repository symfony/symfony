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

use Symfony\Component\Console\Helper\Formatter\BlockFormatter;
use Symfony\Component\Console\Helper\Formatter\FormatterInterface;
use Symfony\Component\Console\Helper\Formatter\ListElementFormatter;
use Symfony\Component\Console\Helper\Formatter\SectionFormatter;
use Symfony\Component\Console\Helper\Formatter\SectionTitleFormatter;
use Symfony\Component\Console\Helper\Formatter\StyledBlockFormatter;
use Symfony\Component\Console\Helper\Formatter\TextFormatter;
use Symfony\Component\Console\Helper\Formatter\TitleFormatter;

/**
 * The Formatter class provides helpers to format messages.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FormatterHelper extends Helper
{
    /**
     * @param FormatterInterface $formatter
     *
     * @return array|string
     */
    public function format(FormatterInterface $formatter)
    {
        return $formatter->format();
    }

    /**
     * Formats a message within a section.
     *
     * @param string $section The section name
     * @param string $message The message
     * @param string $style   The style to apply to the section
     *
     * @return string The format section
     */
    public function formatSection($section, $message, $style = 'info')
    {
        return $this->format(new SectionFormatter($section, $message, $style));
    }

    /**
     * Formats a message as a block of text.
     *
     * @param string|array $messages  The message to write in the block
     * @param string       $style     The style to apply to the whole block
     * @param bool         $large     Whether to return a large block
     * @param int          $padLength Length to pad the messages
     *
     * @return string The formatter message
     */
    public function formatBlock($messages, $style, $large = false, $padLength = 0)
    {
        return $this->format(new BlockFormatter($messages, $style, $large, $padLength));
    }

    /**
     * Formats a command title
     *
     * @param string $message
     *
     * @return array
     */
    public function formatTitle($message)
    {
        return $this->format(new TitleFormatter($message));
    }

    /**
     * Formats a section title
     *
     * @param string $message
     *
     * @return array
     */
    public function formatSectionTitle($message)
    {
        return $this->format(new SectionTitleFormatter($message));
    }

    /**
     * Formats a list element
     *
     * @param string|array $messages
     *
     * @return array
     */
    public function formatListElement($messages)
    {
        return $this->format(new ListElementFormatter($messages));
    }

    /**
     * Formats informational or debug text
     *
     * @param string|array $messages
     *
     * @return string
     */
    public function formatText($messages)
    {
        return $this->format(new TextFormatter($messages));
    }

    /**
     * Formats a success result bar
     *
     * @param string|array $messages
     *
     * @return array
     */
    public function formatSuccessResultBar($messages)
    {
        return $this->format(new StyledBlockFormatter($messages, 'OK', 'fg=white;bg=green'));
    }

    /**
     * Formats an error result bar
     *
     * @param string|array $messages
     *
     * @return array
     */
    public function formatErrorResultBar($messages)
    {
        return $this->format(new StyledBlockFormatter($messages, 'ERROR', 'fg=white;bg=red'));
    }

    /**
     * Formats an warning result bar
     *
     * @param string|array $messages
     *
     * @return array
     */
    public function formatWarningResultBar($messages)
    {
        return $this->format(new StyledBlockFormatter($messages, 'WARNING', 'fg=black;bg=yellow'));
    }

    /**
     * Formats a note admonition
     *
     * @param string|array $messages
     *
     * @return array
     */
    public function formatNoteBlock($messages)
    {
        return $this->format(new StyledBlockFormatter($messages, 'NOTE', 'fg=white', '! '));
    }

    /**
     * Formats a caution admonition
     *
     * @param string|array $messages
     *
     * @return array
     */
    public function formatCautionBlock($messages)
    {
        return $this->format(new StyledBlockFormatter($messages, 'CAUTION', 'fg=white;bg=red', '! '));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'formatter';
    }
}
