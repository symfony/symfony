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
 * Formats informational or debug text
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class TextFormatter implements FormatterInterface
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
        return array_map(function ($value) {
                return sprintf(' // %s', $value);
            },
            (array) $this->messages
        );
    }
}
