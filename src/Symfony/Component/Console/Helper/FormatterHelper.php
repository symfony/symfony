<?php

namespace Symfony\Component\Console\Helper;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * The Formatter class provides helpers to format messages.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FormatterHelper extends Helper
{
    /**
     * Formats a message within a section.
     *
     * @param string  $section The section name
     * @param string  $message The message
     * @param string  $style   The style to apply to the section
     */
    public function formatSection($section, $message, $style = 'info')
    {
        return sprintf('<%s>[%s]</%s> %s', $style, $section, $style, $message);
    }

    /**
     * Formats a message as a block of text.
     *
     * @param string|array $messages The message to write in the block
     * @param string       $style    The style to apply to the whole block
     * @param Boolean      $large    Whether to return a large block
     *
     * @return string The formatter message
     */
    public function formatBlock($messages, $style, $large = false)
    {
        if (!is_array($messages)) {
            $messages = array($messages);
        }

        $len = 0;
        $lines = array();
        foreach ($messages as $message) {
            $lines[] = sprintf($large ? '  %s  ' : ' %s ', $message);
            $len = max($this->strlen($message) + ($large ? 4 : 2), $len);
        }

        $messages = $large ? array(str_repeat(' ', $len)) : array();
        foreach ($lines as $line) {
            $messages[] = $line.str_repeat(' ', $len - $this->strlen($line));
        }
        if ($large) {
            $messages[] = str_repeat(' ', $len);
        }

        foreach ($messages as &$message) {
            $message = sprintf('<%s>%s</%s>', $style, $message, $style);
        }

        return implode("\n", $messages);
    }

    protected function strlen($string)
    {
        return function_exists('mb_strlen') ? mb_strlen($string) : strlen($string);
    }

    /**
     * Returns the helper's canonical name
     */
    public function getName()
    {
        return 'formatter';
    }
}
