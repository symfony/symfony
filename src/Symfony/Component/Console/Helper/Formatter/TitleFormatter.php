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
    protected $underlineChar;
    protected $newLineBefore;

    /**
     * @param string $title
     * @param string $underlineChar
     * @param bool   $newLineBefore
     */
    public function __construct($title, $underlineChar = '=', $newLineBefore = false)
    {
        $this->title = $title;
        $this->underlineChar = $underlineChar;
        $this->newLineBefore = $newLineBefore;
    }

    /**
     * {@inheritdoc}
     */
    public function format()
    {
        $ret = array();

        if ($this->newLineBefore) {
            $ret[] = '';
        }

        $ret[] = sprintf('<fg=blue>%s</fg=blue>', $this->title);
        $ret[] = sprintf('<fg=blue>%s</fg=blue>', str_repeat($this->underlineChar, strlen($this->title)));
        $ret[] = '';

        return implode("\n", $ret);
    }
}
