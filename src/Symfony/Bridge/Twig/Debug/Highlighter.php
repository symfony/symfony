<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Debug;

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
    abstract public function highlight($code, $line = -1, $count = -1);

    /**
     * Returns true if this class is able to highlight the given file name.
     *
     * @param string $name The file name
     *
     * @return bool true if this class supports the given file name, false otherwise
     */
    abstract public function supports($file);

    protected function createLines($lines, $line, $count)
    {
        $code = '';
        $lastOpenSpan = '';

        if ($count < 0) {
            $count = count($lines);
        }

        $from = max(max($line, 1) - $count, 1);
        if ($from > 1 && 0 !== strpos($line[$from - 1], '<span')) {
            for ($i = $from - 2; $i >= 0; $i--) {
                if (preg_match('#^.*(</?span[^>]*>)#', $lines[$i], $match) && '/' != $match[1][1]) {
                    $lastOpenSpan = $match[1];
                    break;
                }
            }
        }

        for ($i = $from, $max = min(max($line, 1) + $count, count($lines)); $i <= $max; $i++) {
            $code .= '<li'.($i == $line ? ' class="selected"' : '').'><code>'.($lastOpenSpan.$lines[$i - 1]);
            if (preg_match('#^.*(</?span[^>]*>)#', $lines[$i - 1], $match)) {
                $lastOpenSpan = '/' != $match[1][1] ? $match[1] : '';
            }

            if ($lastOpenSpan) {
                $code .= '</span>';
            }

            $code .= "</code></li>\n";
        }

        return '<ol class="code" start="'.max($line - $count, 1).'">'.$code.'</ol>';
    }
}
