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
}

