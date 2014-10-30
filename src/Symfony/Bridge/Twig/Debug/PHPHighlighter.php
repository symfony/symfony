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
 * Simple PHP syntax highlighter
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class PHPHighlighter extends Highlighter
{
    private $styles;
    private $regex;

    public function __construct()
    {
        $style = ' style="color: %s"';
        $this->styles = array(
            ' class="comment"' => sprintf($style, ini_get('highlight.comment')),
            ' class="name"' => sprintf($style, ini_get('highlight.default')),
            ' class="tag"' => sprintf($style, ini_get('highlight.html')),
            ' class="operator"' => sprintf($style, ini_get('highlight.keyword')),
            ' class="string"' => sprintf($style, ini_get('highlight.string')),
        );

        $this->regex = '
            /(?:
                (?P<variable>\$[a-zA-Z0-9]+)|
                (?P<keyword>
                    \b(?:__halt_compiler|abstract|and|array|as|break|callable|case|catch|class|clone|const|continue|
                    declare|default|die|do|echo|else|elseif|empty|enddeclare|endfor|endforeach|endif|endswitch|endwhile|eval|exit|extends|
                    final|finally|for|foreach|function|global|goto|if|implements|include|include_once|instanceof|insteadof|interface|isset|
                    list|namespace|new|or|print|private|protected|public|require|require_once|return|static|switch|throw|trait|try|
                    unset|use|var|while|xor|yield)(?![^<"\']*?["\'])\b
                )
            )/ix'
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function highlight($code, $line = -1, $count = -1)
    {
        // highlight_file could throw warnings
        // see https://bugs.php.net/bug.php?id=25725
        $code = @highlight_string($code, true);
        // remove main code/span tags
        $code = preg_replace('#^<code.*?>\s*<span.*?>(.*)</span>\s*</code>#s', '\\1', $code);

        $code = $this->createLines(preg_split('#<br />#', $code), $line, $count);

        $code = str_replace('&nbsp;', ' ', $code);
        $code = str_replace(array_values($this->styles), array_keys($this->styles), $code);
        $code = preg_replace_callback($this->regex, function ($match) {
            $keys = array_keys($match);
            return sprintf('<span class="%s">%s</span>', $keys[count($match) - 2], $match[0]);
        }, $code);

        return $code;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($file)
    {
        return 'php' === pathinfo($file, PATHINFO_EXTENSION);
    }
}
