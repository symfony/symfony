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
 * Simple Twig syntax highlighter
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class TwigHighlighter extends Highlighter
{
    protected $regexString = '"[^"\\\\]*?(?:\\\\.[^"\\\\]*?)*?"|\'[^\'\\\\]*?(?:\\\\.[^\'\\\\]*?)*?\'';
    protected $regexTags;
    protected $regex;

    public function __construct()
    {
        $this->regexTags = '{({{-?|{%-?|{#)((?:'.$this->regexString.'|[^"\']*?)+?)(-?}}|-?%}|#})}s';
        $this->regexKeywords = 'and|or|with';
        $this->regex = '
            /(?:
                (?P<string>'.$this->regexString.')|
                (?P<number>\b\d+(?:\.\d+)?\b)|
                (?P<variable>(?<!\.)\b[a-z][a-z0-9_]*(?=\.|\[|\s*=))|
                (?P<name>\b[a-z0-9_]+(?=\s*\()|(?<=\||\|\s)[a-z0-9_]+\b)|
                (?P<operator>(?:\*\*|\.\.|==|!=|>=|<=|\/\/|\?:|[+\-~\*\/%\.=><\|\(\)\[\]\{\}\?:,]))|
                (?P<keyword>\b(?:if|and|or|b-and|b-xor|b-or|in|matches|starts with|ends with|is|not|as|import|with|true|false|null|none)\b)
            )/xi'
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function highlight($code, $line = -1, $count = -1)
    {
        $regex = $this->regex;
        $code = preg_replace_callback($this->regexTags, function ($matches) use ($regex) {
            if ($matches[1] == '{#') {
                return '<span class="comment">' . $matches[0] . '</span>';
            }

            $matches[2] = preg_replace_callback($regex, function ($match) {
                $keys = array_keys($match);

                return sprintf('<span class="%s">%s</span>', $keys[count($match) - 2], $match[0]);
            }, $matches[2]);

            if ($matches[1][1] == '%') {
                $matches[2] = preg_replace('/^(\s*)([a-z0-9_]+)/i', '\\1<span class="keyword">\\2</span>', $matches[2]);
            }

            return '<span class="tag">'.$matches[1].'</span>'.$matches[2].'<span class="tag">'.$matches[3].'</span>';
        }, htmlspecialchars(str_replace(array("\r\n", "\r"), "\n", $code), ENT_NOQUOTES));

        return $this->createLines(explode("\n", $code), $line, $count);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($file)
    {
        return 'twig' === pathinfo($file, PATHINFO_EXTENSION);
    }
}
