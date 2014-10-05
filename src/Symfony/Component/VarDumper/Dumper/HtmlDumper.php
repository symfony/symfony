<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Dumper;

use Symfony\Component\VarDumper\Cloner\Cursor;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * HtmlDumper dumps variables as HTML.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class HtmlDumper extends CliDumper
{
    public static $defaultOutputStream = 'php://output';

    protected $dumpHeader;
    protected $dumpPrefix = '<pre class=sf-dump>';
    protected $dumpSuffix = '</pre><script>Sfjs.dump.instrument()</script>';
    protected $dumpId = 'sf-dump';
    protected $colors = true;
    protected $headerIsDumped = false;
    protected $lastDepth = -1;
    protected $styles = array(
        'num'       => 'font-weight:bold;color:#0087FF',
        'const'     => 'font-weight:bold;color:#0087FF',
        'str'       => 'font-weight:bold;color:#00D7FF',
        'cchr'      => 'font-style: italic',
        'note'      => 'color:#D7AF00',
        'ref'       => 'color:#444444',
        'public'    => 'color:#008700',
        'protected' => 'color:#D75F00',
        'private'   => 'color:#D70000',
        'meta'      => 'color:#005FFF',
    );

    /**
     * {@inheritdoc}
     */
    public function setLineDumper($callback)
    {
        $this->headerIsDumped = false;

        return parent::setLineDumper($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function setStyles(array $styles)
    {
        $this->headerIsDumped = false;
        $this->styles = $styles + $this->styles;
    }

    /**
     * Sets an HTML header that will be dumped once in the output stream.
     *
     * @param string $header An HTML string.
     */
    public function setDumpHeader($header)
    {
        $this->dumpHeader = $header;
    }

    /**
     * Sets an HTML prefix and suffix that will encapse every single dump.
     *
     * @param string $prefix The prepended HTML string.
     * @param string $suffix The appended HTML string.
     */
    public function setDumpBoundaries($prefix, $suffix)
    {
        $this->dumpPrefix = $prefix;
        $this->dumpSuffix = $suffix;
    }

    /**
     * {@inheritdoc}
     */
    public function dump(Data $data, $lineDumper = null)
    {
        $this->dumpId = 'sf-dump-'.mt_rand();
        parent::dump($data, $lineDumper);
    }

    /**
     * Dumps the HTML header.
     */
    protected function getDumpHeader()
    {
        $this->headerIsDumped = true;

        if (null !== $this->dumpHeader) {
            return $this->dumpHeader;
        }

        $line = <<<'EOHTML'
<script>
Sfjs = window.Sfjs || {};
Sfjs.dump = Sfjs.dump || {};
Sfjs.dump.childElts = Sfjs.dump.childElts || document.getElementsByName('sf-dump-child');
Sfjs.dump.childLen = Sfjs.dump.childLen || 0;
Sfjs.dump.refElts = Sfjs.dump.refElts || document.getElementsByName('sf-dump-ref');
Sfjs.dump.refLen = Sfjs.dump.refLen || 0;
if (!Sfjs.dump.refStyle) {
    Sfjs.dump.refStyle = document.createElement('style');
    document.documentElement.firstChild.appendChild(Sfjs.dump.refStyle);
}
Sfjs.dump.instrument = Sfjs.dump.instrument || function () {
    var elt, i, ref;

    i = this.childLen;
    this.childLen= this.childElts.length;

    while (i < this.childLen) {
        elt = this.childElts[i];
        if ("" == elt.className) {
            elt.className = "sf-dump-child";
            elt.innerHTML = '<a class=sf-dump-ref onclick="Sfjs.dump.toggle(this)"><span>▼</span></a><span class="sf-dump-expanded">'+elt.innerHTML+'</span>';
            if ('sf-dump-expanded' == elt.parentNode.className) {
                if (elt.children[1].firstChild.nextSibling.id) {
                    elt.firstChild.appendChild(elt.children[1].firstChild);
                    elt.firstChild.appendChild(elt.children[1].firstChild);
                }
                Sfjs.dump.toggle(elt.firstChild);
            }
        }
        ++i;
    }

    i = this.refLen;
    this.refLen= this.refElts.length;

    function instrumentRef(elt) {
        var ref = elt.id;

        if (elt.href) {
            ref = elt.getAttribute('href').substr(1);
            if ('@' == elt.innerHTML.charAt(0)) {
                elt.onclick = function() {
                    var r = document.getElementById(ref).parentNode.parentNode,
                        f = r && r.parentNode,
                        t = elt.parentNode,
                        c = elt.cloneNode(true);
                    if (r && r.className == "sf-dump-child") {
                        f.insertBefore(c, r);
                        try {
                            t.replaceChild(r, elt);
                            f.replaceChild(elt, c);
                            Sfjs.dump.refStyle.innerHTML = '';
                            r = r.firstChild;
                            c = r.nextSibling;
                            if ('sf-dump-compact' == c.className) {
                                Sfjs.dump.toggle(r);
                            }

                            return false;
                        } catch (e) {
                            f.removeChild(c);
                        }
                    }
                };
            }
        }
        elt.className += ' '+ref;
        elt.onmouseover = function() {
            Sfjs.dump.refStyle.innerHTML = 'pre.sf-dump .'+ref+'{background-color: yellow; border-radius: 2px}';
        };
        elt.onmouseout = function() {
            Sfjs.dump.refStyle.innerHTML = '';
        };
    }

    while (i < this.refLen) {
        elt = this.refElts[i];
        if ("sf-dump-ref" == elt.className) {
            instrumentRef(elt);
        }
        ++i;
    }
};
Sfjs.dump.toggle = Sfjs.dump.toggle || function(a) {
    var s = a.nextElementSibling;

    if ('sf-dump-compact' == s.className) {
        a.firstChild.innerHTML = '▼';
        s.className = 'sf-dump-expanded';
    } else {
        a.firstChild.innerHTML = '▶';
        s.className = 'sf-dump-compact';
    }
};
</script>
<style>
pre.sf-dump {
    display: block;
    background-color: #300a24;
    white-space: pre;
    line-height: 1.2em;
    color: #eee8d5;
    font: 12px monospace, sans-serif;
    padding: 5px;
    border-radius: 5px;
}
pre.sf-dump span {
    display: inline;
}
pre.sf-dump .sf-dump-compact {
    display: none;
}
pre.sf-dump abbr {
    text-decoration: none;
    border: none;
    cursor: help;
}
pre.sf-dump a {
    text-decoration: none;
    cursor: pointer;
}
EOHTML;

        foreach ($this->styles as $class => $style) {
            $line .= "pre.sf-dump .sf-dump-$class {{$style}}";
        }

        return $this->dumpHeader = preg_replace('/\s+/', ' ', $line).'</style>'.$this->dumpHeader;
    }

    /**
     * {@inheritdoc}
     */
    protected function enterHash(Cursor $cursor, $prefix, $hasChild)
    {
        if ($hasChild) {
            $prefix .= '<span name=sf-dump-child>';
        }

        return parent::enterHash($cursor, $prefix, $hasChild);
    }

    /**
     * {@inheritdoc}
     */
    protected function leaveHash(Cursor $cursor, $suffix, $hasChild, $cut)
    {
        if ($hasChild) {
            $suffix = '</span>'.$suffix;
        }

        return parent::leaveHash($cursor, $suffix, $hasChild, $cut);
    }

    /**
     * {@inheritdoc}
     */
    protected function style($style, $val)
    {
        if ('' === $val) {
            return '';
        }

        if ('ref' === $style) {
            $ref = substr($val, 1);
            if ('#' === $val[0]) {
                return "<span class=sf-dump-ref name=sf-dump-ref id=\"{$this->dumpId}-ref$ref\">$val</span>";
            } else {
                return "<a class=sf-dump-ref name=sf-dump-ref href=\"#{$this->dumpId}-ref$ref\">$val</a>";
            }
        }

        $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');

        if ('str' === $style || 'meta' === $style || 'public' === $style) {
            foreach (static::$controlChars as $c) {
                if (false !== strpos($val, $c)) {
                    $r = "\x7F" === $c ? '?' : chr(64 + ord($c));
                    $val = str_replace($c, "<span class=sf-dump-cchr>$r</span>", $val);
                }
            }
        } elseif ('note' === $style) {
            if (false !== $c = strrpos($val, '\\')) {
                return sprintf('<abbr title="%s" class=sf-dump-%s>%s</abbr>', $val, $style, substr($val, $c+1));
            } elseif (':' === $val[0]) {
                return sprintf('<abbr title="Resource of type `%s`" class=sf-dump-%s>%s</abbr>', substr($val, 1), $style, $val);
            }
        }

        return "<span class=sf-dump-$style>$val</span>";
    }

    /**
     * {@inheritdoc}
     */
    protected function dumpLine($depth)
    {
        if (-1 === $this->lastDepth) {
            $this->line = $this->dumpPrefix.$this->line;
        }
        if (!$this->headerIsDumped) {
            $this->line = $this->getDumpHeader().$this->line;
        }

        if (-1 === $depth) {
            $this->line .= $this->dumpSuffix;
            parent::dumpLine(0);
        }
        $this->lastDepth = $depth;

        // Replaces non-ASCII UTF-8 chars by numeric HTML entities
        $this->line = preg_replace_callback(
            '/[\x80-\xFF]+/',
            function ($m) {
                $m = unpack('C*', $m[0]);
                $i = 1;
                $entities = '';

                while (isset($m[$i])) {
                    if (0xF0 <= $m[$i]) {
                        $c = (($m[$i++] - 0xF0) << 18) + (($m[$i++] - 0x80) << 12) + (($m[$i++] - 0x80) << 6) + $m[$i++] - 0x80;
                    } elseif (0xE0 <= $m[$i]) {
                        $c = (($m[$i++] - 0xE0) << 12) + (($m[$i++] - 0x80) << 6) + $m[$i++]  - 0x80;
                    } else {
                        $c = (($m[$i++] - 0xC0) << 6) + $m[$i++] - 0x80;
                    }

                    $entities .= '&#'.$c.';';
                }

                return $entities;
            },
            $this->line
        );

        parent::dumpLine($depth);
    }
}
