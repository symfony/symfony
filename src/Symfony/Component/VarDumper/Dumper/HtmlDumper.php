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
    public static $defaultOutput = 'php://output';

    protected $dumpHeader;
    protected $dumpPrefix = '<pre class=sf-dump id=%s data-indent-pad="%s">';
    protected $dumpSuffix = '</pre><script>Sfdump("%s")</script>';
    protected $dumpId = 'sf-dump';
    protected $colors = true;
    protected $headerIsDumped = false;
    protected $lastDepth = -1;
    protected $styles = array(
        'default' => 'background-color:#18171B; color:#FF8400; line-height:1.2em; font:12px Menlo, Monaco, Consolas, monospace; word-wrap: break-word; white-space: pre-wrap; position:relative; z-index:100000; word-break: normal',
        'num' => 'font-weight:bold; color:#1299DA',
        'const' => 'font-weight:bold',
        'str' => 'font-weight:bold; color:#56DB3A',
        'note' => 'color:#1299DA',
        'ref' => 'color:#A0A0A0',
        'public' => 'color:#FFFFFF',
        'protected' => 'color:#FFFFFF',
        'private' => 'color:#FFFFFF',
        'meta' => 'color:#B729D9',
        'key' => 'color:#56DB3A',
        'index' => 'color:#1299DA',
    );

    /**
     * {@inheritdoc}
     */
    public function __construct($output = null, $charset = null)
    {
        AbstractDumper::__construct($output, $charset);
        $this->dumpId = 'sf-dump-'.mt_rand();
    }

    /**
     * {@inheritdoc}
     */
    public function setOutput($output)
    {
        if ($output !== $prev = parent::setOutput($output)) {
            $this->headerIsDumped = false;
        }

        return $prev;
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
    public function dump(Data $data, $output = null)
    {
        parent::dump($data, $output);
        $this->dumpId = 'sf-dump-'.mt_rand();
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
Sfdump = window.Sfdump || (function (doc) {

var refStyle = doc.createElement('style'),
    rxEsc = /([.*+?^${}()|\[\]\/\\])/g,
    idRx = /\bsf-dump-\d+-ref[012]\w+\b/,
    keyHint = 0 <= navigator.platform.toUpperCase().indexOf('MAC') ? 'Cmd' : 'Ctrl',
    addEventListener = function (e, n, cb) {
        e.addEventListener(n, cb, false);
    };

(doc.documentElement.firstElementChild || doc.documentElement.children[0]).appendChild(refStyle);

if (!doc.addEventListener) {
    addEventListener = function (element, eventName, callback) {
        element.attachEvent('on' + eventName, function (e) {
            e.preventDefault = function () {e.returnValue = false;};
            e.target = e.srcElement;
            callback(e);
        });
    };
}

function toggle(a, recursive) {
    var s = a.nextSibling || {}, oldClass = s.className, arrow, newClass;

    if ('sf-dump-compact' == oldClass) {
        arrow = '▼';
        newClass = 'sf-dump-expanded';
    } else if ('sf-dump-expanded' == oldClass) {
        arrow = '▶';
        newClass = 'sf-dump-compact';
    } else {
        return false;
    }

    a.lastChild.innerHTML = arrow;
    s.className = newClass;

    if (recursive) {
        try {
            a = s.querySelectorAll('.'+oldClass);
            for (s = 0; s < a.length; ++s) {
                if (a[s].className !== newClass) {
                    a[s].className = newClass;
                    a[s].previousSibling.lastChild.innerHTML = arrow;
                }
            }
        } catch (e) {
        }
    }

    return true;
};

return function (root) {
    root = doc.getElementById(root);

    function a(e, f) {
        addEventListener(root, e, function (e) {
            if ('A' == e.target.tagName) {
                f(e.target, e);
            } else if ('A' == e.target.parentNode.tagName) {
                f(e.target.parentNode, e);
            }
        });
    };
    function isCtrlKey(e) {
        return e.ctrlKey || e.metaKey;
    }
    addEventListener(root, 'mouseover', function (e) {
        if ('' != refStyle.innerHTML) {
            refStyle.innerHTML = '';
        }
    });
    a('mouseover', function (a) {
        if (a = idRx.exec(a.className)) {
            try {
                refStyle.innerHTML = 'pre.sf-dump .'+a[0]+'{background-color: #B729D9; color: #FFF !important; border-radius: 2px}';
            } catch (e) {
            }
        }
    });
    a('click', function (a, e) {
        if (/\bsf-dump-toggle\b/.test(a.className)) {
            e.preventDefault();
            if (!toggle(a, isCtrlKey(e))) {
                var r = doc.getElementById(a.getAttribute('href').substr(1)),
                    s = r.previousSibling,
                    f = r.parentNode,
                    t = a.parentNode;
                t.replaceChild(r, a);
                f.replaceChild(a, s);
                t.insertBefore(s, r);
                f = f.firstChild.nodeValue.match(indentRx);
                t = t.firstChild.nodeValue.match(indentRx);
                if (f && t && f[0] !== t[0]) {
                    r.innerHTML = r.innerHTML.replace(new RegExp('^'+f[0].replace(rxEsc, '\\$1'), 'mg'), t[0]);
                }
                if ('sf-dump-compact' == r.className) {
                    toggle(s, isCtrlKey(e));
                }
            }

            if (doc.getSelection) {
                try {
                    doc.getSelection().removeAllRanges();
                } catch (e) {
                    doc.getSelection().empty();
                }
            } else {
                doc.selection.empty();
            }
        }
    });

    var indentRx = new RegExp('^('+(root.getAttribute('data-indent-pad') || '  ').replace(rxEsc, '\\$1')+')+', 'm'),
        elt = root.getElementsByTagName('A'),
        len = elt.length,
        i = 0,
        t = [];

    while (i < len) t.push(elt[i++]);

    elt = root.getElementsByTagName('SAMP');
    len = elt.length;
    i = 0;

    while (i < len) t.push(elt[i++]);

    root = t;
    len = t.length;
    i = t = 0;

    while (i < len) {
        elt = root[i];
        if ("SAMP" == elt.tagName) {
            elt.className = "sf-dump-expanded";
            a = elt.previousSibling || {};
            if ('A' != a.tagName) {
                a = doc.createElement('A');
                a.className = 'sf-dump-ref';
                elt.parentNode.insertBefore(a, elt);
            } else {
                a.innerHTML += ' ';
            }
            a.title = (a.title ? a.title+'\n[' : '[')+keyHint+'+click] Expand all children';
            a.innerHTML += '<span>▼</span>';
            a.className += ' sf-dump-toggle';
            if ('sf-dump' != elt.parentNode.className) {
                toggle(a);
            }
        } else if ("sf-dump-ref" == elt.className && (a = elt.getAttribute('href'))) {
            a = a.substr(1);
            elt.className += ' '+a;

            if (/[\[{]$/.test(elt.previousSibling.nodeValue)) {
                a = a != elt.nextSibling.id && doc.getElementById(a);
                try {
                    t = a.nextSibling;
                    elt.appendChild(a);
                    t.parentNode.insertBefore(a, t);
                    if (/^[@#]/.test(elt.innerHTML)) {
                        elt.innerHTML += ' <span>▶</span>';
                    } else {
                        elt.innerHTML = '<span>▶</span>';
                        elt.className = 'sf-dump-ref';
                    }
                    elt.className += ' sf-dump-toggle';
                } catch (e) {
                    if ('&' == elt.innerHTML.charAt(0)) {
                        elt.innerHTML = '…';
                        elt.className = 'sf-dump-ref';
                    }
                }
            }
        }
        ++i;
    }
};

})(document);
</script>
<style>
pre.sf-dump {
    display: block;
    white-space: pre;
    padding: 5px;
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
    border: 0;
    outline: none;
}
EOHTML;

        foreach ($this->styles as $class => $style) {
            $line .= 'pre.sf-dump'.('default' !== $class ? ' .sf-dump-'.$class : '').'{'.$style.'}';
        }

        return $this->dumpHeader = preg_replace('/\s+/', ' ', $line).'</style>'.$this->dumpHeader;
    }

    /**
     * {@inheritdoc}
     */
    public function enterHash(Cursor $cursor, $type, $class, $hasChild)
    {
        parent::enterHash($cursor, $type, $class, false);

        if ($hasChild) {
            if ($cursor->refIndex) {
                $r = Cursor::HASH_OBJECT !== $type ? 1 - (Cursor::HASH_RESOURCE !== $type) : 2;
                $r .= $r && 0 < $cursor->softRefHandle ? $cursor->softRefHandle : $cursor->refIndex;

                $this->line .= sprintf('<samp id=%s-ref%s>', $this->dumpId, $r);
            } else {
                $this->line .= '<samp>';
            }
            $this->dumpLine($cursor->depth);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function leaveHash(Cursor $cursor, $type, $class, $hasChild, $cut)
    {
        $this->dumpEllipsis($cursor, $hasChild, $cut);
        if ($hasChild) {
            $this->line .= '</samp>';
        }
        parent::leaveHash($cursor, $type, $class, $hasChild, 0);
    }

    /**
     * {@inheritdoc}
     */
    protected function style($style, $value, $attr = array())
    {
        if ('' === $value) {
            return '';
        }

        $v = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        if ('ref' === $style) {
            if (empty($attr['count'])) {
                return sprintf('<a class=sf-dump-ref>%s</a>', $v);
            }
            $r = ('#' !== $v[0] ? 1 - ('@' !== $v[0]) : 2).substr($value, 1);

            return sprintf('<a class=sf-dump-ref href=#%s-ref%s title="%d occurrences">%s</a>', $this->dumpId, $r, 1 + $attr['count'], $v);
        }

        if ('const' === $style && array_key_exists('value', $attr)) {
            $style .= sprintf(' title="%s"', htmlspecialchars(json_encode($attr['value']), ENT_QUOTES, 'UTF-8'));
        } elseif ('public' === $style) {
            $style .= sprintf(' title="%s"', empty($attr['dynamic']) ? 'Public property' : 'Runtime added dynamic property');
        } elseif ('str' === $style && 1 < $attr['length']) {
            $style .= sprintf(' title="%s%s characters"', $attr['length'], $attr['binary'] ? ' binary or non-UTF-8' : '');
        } elseif ('note' === $style && false !== $c = strrpos($v, '\\')) {
            return sprintf('<abbr title="%s" class=sf-dump-%s>%s</abbr>', $v, $style, substr($v, $c + 1));
        } elseif ('protected' === $style) {
            $style .= ' title="Protected property"';
        } elseif ('private' === $style) {
            $style .= sprintf(' title="Private property defined in class:&#10;`%s`"', $attr['class']);
        }

        $map = static::$controlCharsMap;
        $style = "<span class=sf-dump-{$style}>";
        $v = preg_replace_callback(static::$controlCharsRx, function ($c) use ($map, $style) {
            $s = '</span>';
            $c = $c[$i = 0];
            do {
                $s .= isset($map[$c[$i]]) ? $map[$c[$i]] : sprintf('\x%02X', ord($c[$i]));
            } while (isset($c[++$i]));

            return $s.$style;
        }, $v, -1, $cchrCount);

        if ($cchrCount && '<' === $v[0]) {
            $v = substr($v, 7);
        } else {
            $v = $style.$v;
        }
        if ($cchrCount && '>' === substr($v, -1)) {
            $v = substr($v, 0, -strlen($style));
        } else {
            $v .= '</span>';
        }

        return $v;
    }

    /**
     * {@inheritdoc}
     */
    protected function dumpLine($depth, $endOfValue = false)
    {
        if (-1 === $this->lastDepth) {
            $this->line = sprintf($this->dumpPrefix, $this->dumpId, $this->indentPad).$this->line;
        }
        if (!$this->headerIsDumped) {
            $this->line = $this->getDumpHeader().$this->line;
        }

        if (-1 === $depth) {
            $this->line .= sprintf($this->dumpSuffix, $this->dumpId);
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

        if (-1 === $depth) {
            AbstractDumper::dumpLine(0);
        }
        AbstractDumper::dumpLine($depth);
    }
}
