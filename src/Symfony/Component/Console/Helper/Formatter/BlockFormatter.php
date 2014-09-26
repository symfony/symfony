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
    const MAX_LENGTH = 120;

    protected $messages;
    protected $type;
    protected $style;
    protected $prefix;

    /**
     * @param string|array $messages  The message to write in the block
     * @param string|null  $type      The block type (added in [] on first line)
     * @param string|null  $style     The style to apply to the whole block
     * @param string       $prefix    The prefix for the block
     */
    public function __construct($messages, $type = null, $style = null, $prefix = ' ')
    {
        $this->messages = $messages;
        $this->type = $type;
        $this->style = $style;
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function format()
    {
        $messages = array_values((array) $this->messages);
        $lines = array();

        // add type
        if (null !== $this->type) {
            $messages[0] = sprintf('[%s] %s', $this->type, $messages[0]);
        }

        // wrap and add newlines for each element
        foreach ($messages as $key => $message) {
            $message = OutputFormatter::escape($message);
            $lines = array_merge($lines, explode("\n", wordwrap($message, self::MAX_LENGTH - Helper::strlen($this->prefix))));

            if (count($messages) > 1 && $key < count($message)) {
                $lines[] = '';
            }
        }

        foreach ($lines as &$line) {
            $line = sprintf('%s%s', $this->prefix, $line);
            $line .= str_repeat(' ', self::MAX_LENGTH - Helper::strlen($line));

            if ($this->style) {
                $line = sprintf('<%s>%s</%s>', $this->style, $line, $this->style);
            }
        }

        return implode("\n", $lines)."\n";
    }
}
