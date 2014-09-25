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

/**
 * Formats a list element
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class ListFormatter implements FormatterInterface
{
    protected $messages;

    /**
     * @param string|array $messages
     */
    public function __construct($messages)
    {
        $this->messages = $messages;
    }

    /**
     * {@inheritdoc}
     */
    public function format()
    {
        $ret = array();

        foreach ((array) $this->messages as $message) {
            $ret[] = sprintf(' * %s', $message);
            $ret[] = '';
        }

        return $ret;
    }
}
