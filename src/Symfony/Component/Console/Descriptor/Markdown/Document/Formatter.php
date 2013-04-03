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
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Formatter
{
    /**
     * @var int
     */
    private $maxWidth;

    /**
     * @param int $maxWidth
     */
    public function __construct($maxWidth = 120)
    {
        $this->maxWidth = $maxWidth;
    }

    /**
     * Clips content on words.
     *
     * @param string $content   Content to clip
     * @param int    $freeSpace Free space to keep
     *
     * @return array
     */
    public function clip($content, $freeSpace = 0)
    {
        $lines = array();
        $maxWidth = $this->maxWidth - $freeSpace;

        foreach (explode("\n", $content) as $text) {
            $line = '';
            foreach (explode(' ', $text) as $word) {
                if (strlen($line.$word) <= $maxWidth) {
                    $line .= $word.' ';
                    continue;
                }

                $lines[] = substr($line, 0, -1);
                $line = $word.' ';
            }

            if (!empty($line)) {
                $lines[] = substr($line, 0, -1);
            }
        }

        return $lines;
    }
}
