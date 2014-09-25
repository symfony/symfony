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

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * The Formatter class provides helpers to format messages.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FormatterHelper extends Helper
{
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
        return sprintf('<%s>[%s]</%s> %s', $style, $section, $style, $message);
    }

    /**
     * Formats a message as a block of text.
     *
     * @param string|array $messages  The message to write in the block
     * @param string       $style     The style to apply to the whole block
     * @param bool         $large     Whether to return a large block
     * @param int          $padLength ength to pad the messages
     *
     * @return string The formatter message
     */
    public function formatBlock($messages, $style, $large = false, $padLength = 0)
    {
        if (!is_array($messages)) {
            $messages = array($messages);
        }

        $len = 0;
        $lines = array();
        foreach ($messages as $message) {
            $message = OutputFormatter::escape($message);
            $lines[] = sprintf($large ? '  %s  ' : ' %s ', $message);
            $len = max($this->strlen($message) + ($large ? 4 : 2), $len);
        }

        $messages = $large ? array(str_repeat(' ', $len)) : array();
        for ($i = 0; isset($lines[$i]); ++$i) {
            $messages[] = $lines[$i].str_repeat(' ', $len - $this->strlen($lines[$i]));
        }
        if ($large) {
            $messages[] = str_repeat(' ', $len);
        }

        for ($i = 0; isset($messages[$i]); ++$i) {
            $messages[$i] = sprintf('<%s>%s</%s>', $style, str_pad($messages[$i], $padLength), $style);
        }

        return implode("\n", $messages);
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
        return array(
            '',
            sprintf('<fg=blue>%s</fg=blue>', $message),
            sprintf('<fg=blue>%s</fg=blue>', str_repeat('=', strlen($message))),
            ''
        );
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
        return array(
            sprintf('<fg=blue>%s</fg=blue>', $message),
            sprintf('<fg=blue>%s</fg=blue>', str_repeat('-', strlen($message))),
            ''
        );
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
        $messages = array_values((array) $messages);

        $messages[0] = sprintf(' * %s', $messages[0]);

        foreach ($messages as $key => &$message) {
            if (0 === $key) {
                continue;
            }

            $message = sprintf('   %s', $message);
        }

        return array_merge($messages, array(''));
    }

    /**
     * Formats informational or debug text
     *
     * @param string $message
     *
     * @return string
     */
    public function formatText($message)
    {
        return sprintf(' // %s', $message);
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
        return $this->formatStyledBlock($messages, 'OK', 'fg=white;bg=green');
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
        return $this->formatStyledBlock($messages, 'ERROR', 'fg=white;bg=red');
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
        return $this->formatStyledBlock($messages, 'NOTE', 'fg=white', '! ');
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
        return $this->formatStyledBlock($messages, 'CAUTION', 'fg=white;bg=red', '! ');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'formatter';
    }

    /**
     * Formats a styled block
     *
     * @param string|array $messages
     * @param string       $type
     * @param string       $style
     * @param string $prefix
     *
     * @return array
     */
    protected function formatStyledBlock($messages, $type, $style, $prefix = '')
    {
        $messages = array_values((array) $messages);

        $messages[0] = sprintf('[%s] %s', $type, $messages[0]);

        $messages = array_map(function ($value) use ($prefix) {
                return sprintf('%s%s', $prefix, $value);
            },
            $messages
        );

        return array(
            $this->formatBlock($messages, $style, false, 120),
            ''
        );
    }
}
