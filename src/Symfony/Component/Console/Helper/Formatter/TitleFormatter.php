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
 * Formats a command title
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class TitleFormatter implements FormatterInterface
{
    protected $title;

    /**
     * @param string $title
     */
    public function __construct($title)
    {
        $this->title = $title;
    }

    /**
     * {@inheritdoc}
     */
    public function format()
    {
        return implode("\n", array(
            '',
            sprintf('<fg=blue>%s</fg=blue>', $this->title),
            sprintf('<fg=blue>%s</fg=blue>', str_repeat('=', strlen($this->title))),
            ''
        ));
    }
}
