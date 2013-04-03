<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Markdown\Document;

/**
 * Document paragraph.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Title implements BlockInterface
{
    /**
     * @var string
     */
    private $content;

    /**
     * @var int
     */
    private $level;

    /**
     * @param string $content
     * @param int    $level
     */
    public function __construct($content, $level = 1)
    {
        $this->content = $content;
        $this->level = $level;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return empty($this->content);
    }

    /**
     * {@inheritdoc}
     */
    public function format(Formatter $formatter)
    {
        if (empty($this->content)) {
            return '';
        }

        switch ($this->level) {
            case 1: return $this->entitle('=');
            case 2: return $this->entitle('-');
        }

        $lines = array();
        foreach ($formatter->clip($this->content, $this->level + 1) as $index => $line) {
            $lines[] = (0 === $index ? str_repeat('#', $this->level).' ' : str_repeat(' ', $this->level + 1)).$line;
        }

        return implode("\n", $lines);
    }

    /**
     * @param string $char
     *
     * @return string
     */
    private function entitle($char)
    {
        return $this->content."\n".str_repeat($char, strlen($this->content));
    }
}
