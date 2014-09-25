<?php

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
        return array(
            '',
            sprintf('<fg=blue>%s</fg=blue>', $this->title),
            sprintf('<fg=blue>%s</fg=blue>', str_repeat('=', strlen($this->title))),
            ''
        );
    }
}
