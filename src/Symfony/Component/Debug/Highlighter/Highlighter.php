<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Highlighter;

/**
 * The base class for syntax highlighters
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
abstract class Highlighter
{
    /**
     * Highlights a code
     *
     * @param string $code  The code
     * @param int    $line  The selected line number
     * @param int    $count The number of lines above and below the selected line
     *
     * @return string The highlighted code
     */
    abstract public function highlight($code, $from = 1, $to = -1, $line = -1);

    /**
     * Returns true if this class is able to highlight the given file name.
     *
     * @param string $name The file name
     *
     * @return bool true if this class supports the given file name, false otherwise
     */
    abstract public function supports($file);

    protected function createLines($lines, $from, $to, $line = null)
    {
        $code = '';
        $lastOpenSpan = '';
        $to = -1 === $to ? count($lines) : $to;

        $slice = array_slice($lines, $from - 1, $to - $from + 1, true);
        $key = key($slice);
        if (isset($lines[$key - 1]) && 0 !== strpos($lines[$key - 1], '<span')) {
            for ($i = $key - 2; $i >= 0; $i--) {
                if (isset($lines[$i]) && preg_match('#^.*(</?span[^>]*>)#', $lines[$i], $match) && '/' != $match[1][1]) {
                    $lastOpenSpan = $match[1];
                    break;
                }
            }
        }

        --$line;
        foreach ($slice as $number => $content) {
            $code .= '<li'.($number === $line ? ' class="selected"' : '').'><code>'.($lastOpenSpan.$content);
            if (preg_match('#^.*(</?span[^>]*>)#', $content, $match)) {
                $lastOpenSpan = '/' != $match[1][1] ? $match[1] : '';
            }

            if ($lastOpenSpan) {
                $code .= '</span>';
            }

            $code .= "</code></li>\n";
        }

        return '<ol class="code" start="'.($key+1).'">'.$code.'</ol>';
    }
}
