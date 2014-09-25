<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper\Formatter;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;

/**
 * Formats a message as a block of text.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class BlockFormatter implements FormatterInterface
{
    protected $messages;
    protected $style;
    protected $large;
    protected $padLength;

    /**
     * @param string|array $messages  The message to write in the block
     * @param string       $style     The style to apply to the whole block
     * @param bool         $large     Whether to return a large block
     * @param int          $padLength Length to pad the messages
     */
    public function __construct($messages, $style, $large = false, $padLength = 0)
    {
        $this->messages = $messages;
        $this->style = $style;
        $this->large = $large;
        $this->padLength = $padLength;
    }

    /**
     * {@inheritdoc}
     */
    public function format()
    {
        $messages = (array) $this->messages;

        $len = 0;
        $lines = array();
        foreach ($messages as $message) {
            $message = OutputFormatter::escape($message);
            $lines[] = sprintf($this->large ? '  %s  ' : ' %s ', $message);
            $len = max(Helper::strlen($message) + ($this->large ? 4 : 2), $len);
        }

        $messages = $this->large ? array(str_repeat(' ', $len)) : array();
        foreach ($lines as $line) {
            $messages[] = $line.str_repeat(' ', $len - Helper::strlen($line));
        }
        if ($this->large) {
            $messages[] = str_repeat(' ', $len);
        }

        foreach ($messages as &$message) {
            $padding = $this->padLength - Helper::strlen($message);
            $message = sprintf('<%s>%s</%s>', $this->style, $message.str_repeat(' ', $padding > 0 ? $padding : 0), $this->style);
        }

        return implode("\n", $messages);
    }
}
