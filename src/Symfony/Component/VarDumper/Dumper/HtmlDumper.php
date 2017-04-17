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
    protected $dumpSuffix = '</pre><script>Sfdump(%s)</script>';
    protected $dumpId = 'sf-dump';
    protected $colors = true;
    protected $headerIsDumped = false;
    protected $lastDepth = -1;
    protected $styles = array(
        'default' => 'background-color:#18171B; color:#FF8400; line-height:1.2em; font:12px Menlo, Monaco, Consolas, monospace; word-wrap: break-word; white-space: pre-wrap; position:relative; z-index:99999; word-break: normal',
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
        'ellipsis' => 'color:#FF8400',
    );

    private $displayOptions = array(
        'maxDepth' => 1,
        'maxStringLength' => 160,
        'fileLinkFormat' => null,
    );
    private $extraDisplayOptions = array();

    /**
     * {@inheritdoc}
     */
    public function __construct($output = null, $charset = null, $flags = 0)
    {
        AbstractDumper::__construct($output, $charset, $flags);
        $this->dumpId = 'sf-dump-'.mt_rand();
        $this->displayOptions['fileLinkFormat'] = ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
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
     * Configures display options.
     *
     * @param array $displayOptions A map of display options to customize the behavior
     */
    public function setDisplayOptions(array $displayOptions)
    {
        $this->headerIsDumped = false;
        $this->displayOptions = $displayOptions + $this->displayOptions;
    }

    /**
     * Sets an HTML header that will be dumped once in the output stream.
     *
     * @param string $header An HTML string
     */
    public function setDumpHeader($header)
    {
        $this->dumpHeader = $header;
    }

    /**
     * Sets an HTML prefix and suffix that will encapse every single dump.
     *
     * @param string $prefix The prepended HTML string
     * @param string $suffix The appended HTML string
     */
    public function setDumpBoundaries($prefix, $suffix)
    {
        $this->dumpPrefix = $prefix;
        $this->dumpSuffix = $suffix;
    }

    /**
     * {@inheritdoc}
     */
    public function dump(Data $data, $output = null, array $extraDisplayOptions = array())
    {
        $this->extraDisplayOptions = $extraDisplayOptions;
        $result = parent::dump($data, $output);
        $this->dumpId = 'sf-dump-'.mt_rand();

        return $result;
    }

    /**
     * Dumps the HTML header.
     */
    protected function getDumpHeader()
    {
        $this->headerIsDumped = null !== $this->outputStream ? $this->outputStream : $this->lineDumper;

        if (null !== $this->dumpHeader) {
            return $this->dumpHeader;
        }

        $line = str_replace('{$options}', json_encode($this->displayOptions, JSON_FORCE_OBJECT), <<<'EOHTML'
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

return function (root, x) {
    root = doc.getElementById(root);

    var indentRx = new RegExp('^('+(root.getAttribute('data-indent-pad') || '  ').replace(rxEsc, '\\$1')+')+', 'm'),
        options = {$options},
        elt = root.getElementsByTagName('A'),
        len = elt.length,
        i = 0, s, h,
        t = [];

    while (i < len) t.push(elt[i++]);

    for (i in x) {
        options[i] = x[i];
    }

    function a(e, f) {
        addEventListener(root, e, function (e) {
            if ('A' == e.target.tagName) {
                f(e.target, e);
            } else if ('A' == e.target.parentNode.tagName) {
                f(e.target.parentNode, e);
            } else if (e.target.nextElementSibling && 'A' == e.target.nextElementSibling.tagName) {
                f(e.target.nextElementSibling, e, true);
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
    a('mouseover', function (a, e, c) {
        if (c) {
            e.target.style.cursor = "pointer";
        } else if (a = idRx.exec(a.className)) {
            try {
                refStyle.innerHTML = 'pre.sf-dump .'+a[0]+'{background-color: #B729D9; color: #FFF !important; border-radius: 2px}';
            } catch (e) {
            }
        }
    });
    a('click', function (a, e, c) {
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

            if (c) {
            } else if (doc.getSelection) {
                try {
                    doc.getSelection().removeAllRanges();
                } catch (e) {
                    doc.getSelection().empty();
                }
            } else {
                doc.selection.empty();
            }
        } else if (/\bsf-dump-str-toggle\b/.test(a.className)) {
            e.preventDefault();
            e = a.parentNode.parentNode;
            e.className = e.className.replace(/sf-dump-str-(expand|collapse)/, a.parentNode.className);
        }
    });

    elt = root.getElementsByTagName('SAMP');
    len = elt.length;
    i = 0;

    while (i < len) t.push(elt[i++]);
    len = t.length;

    for (i = 0; i < len; ++i) {
        elt = t[i];
        if ('SAMP' == elt.tagName) {
            elt.className = 'sf-dump-expanded';
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
            x = 1;
            if ('sf-dump' != elt.parentNode.className) {
                x += elt.parentNode.getAttribute('data-depth')/1;
            }
            elt.setAttribute('data-depth', x);
            if (x > options.maxDepth) {
                toggle(a);
            }
        } else if ('sf-dump-ref' == elt.className && (a = elt.getAttribute('href'))) {
            a = a.substr(1);
            elt.className += ' '+a;

            if (/[\[{]$/.test(elt.previousSibling.nodeValue)) {
                a = a != elt.nextSibling.id && doc.getElementById(a);
                try {
                    s = a.nextSibling;
                    elt.appendChild(a);
                    s.parentNode.insertBefore(a, s);
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
    }

    if (0 >= options.maxStringLength) {
        return;
    }
    try {
        elt = root.querySelectorAll('.sf-dump-str');
        len = elt.length;
        i = 0;
        t = [];

        while (i < len) t.push(elt[i++]);
        len = t.length;

        for (i = 0; i < len; ++i) {
            elt = t[i];
            s = elt.innerText || elt.textContent;
            x = s.length - options.maxStringLength;
            if (0 < x) {
                h = elt.innerHTML;
                elt[elt.innerText ? 'innerText' : 'textContent'] = s.substring(0, options.maxStringLength);
                elt.className += ' sf-dump-str-collapse';
                elt.innerHTML = '<span class=sf-dump-str-collapse>'+h+'<a class="sf-dump-ref sf-dump-str-toggle" title="Collapse"> ◀</a></span>'+
                    '<span class=sf-dump-str-expand>'+elt.innerHTML+'<a class="sf-dump-ref sf-dump-str-toggle" title="'+x+' remaining characters"> ▶</a></span>';
            }
        }
    } catch (e) {
    }
};

})(document);
</script><style>
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
    color: inherit;
}
pre.sf-dump .sf-dump-ellipsis {
    display: inline-block;
    overflow: visible;
    text-overflow: ellipsis;
    max-width: 5em;
    white-space: nowrap;
    overflow: hidden;
    vertical-align: top;
}
pre.sf-dump code {
    display:inline;
    padding:0;
    background:none;
}
.sf-dump-str-collapse .sf-dump-str-collapse {
    display: none;
}
.sf-dump-str-expand .sf-dump-str-expand {
    display: none;
}
EOHTML
        );

        foreach ($this->styles as $class => $style) {
            $line .= 'pre.sf-dump'.('default' === $class ? ', pre.sf-dump' : '').' .sf-dump-'.$class.'{'.$style.'}';
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

        $v = esc($value);

        if ('ref' === $style) {
            if (empty($attr['count'])) {
                return sprintf('<a class=sf-dump-ref>%s</a>', $v);
            }
            $r = ('#' !== $v[0] ? 1 - ('@' !== $v[0]) : 2).substr($value, 1);

            return sprintf('<a class=sf-dump-ref href=#%s-ref%s title="%d occurrences">%s</a>', $this->dumpId, $r, 1 + $attr['count'], $v);
        }

        if ('const' === $style && isset($attr['value'])) {
            $style .= sprintf(' title="%s"', esc(is_scalar($attr['value']) ? $attr['value'] : json_encode($attr['value'])));
        } elseif ('public' === $style) {
            $style .= sprintf(' title="%s"', empty($attr['dynamic']) ? 'Public property' : 'Runtime added dynamic property');
        } elseif ('str' === $style && 1 < $attr['length']) {
            $style .= sprintf(' title="%d%s characters"', $attr['length'], $attr['binary'] ? ' binary or non-UTF-8' : '');
        } elseif ('note' === $style && false !== $c = strrpos($v, '\\')) {
            return sprintf('<abbr title="%s" class=sf-dump-%s>%s</abbr>', $v, $style, substr($v, $c + 1));
        } elseif ('protected' === $style) {
            $style .= ' title="Protected property"';
        } elseif ('meta' === $style && isset($attr['title'])) {
            $style .= sprintf(' title="%s"', esc($this->utf8Encode($attr['title'])));
        } elseif ('private' === $style) {
            $style .= sprintf(' title="Private property defined in class:&#10;`%s`"', esc($this->utf8Encode($attr['class'])));
        }
        $map = static::$controlCharsMap;

        if (isset($attr['ellipsis'])) {
            $label = esc(substr($value, -$attr['ellipsis']));
            $style = str_replace(' title="', " title=\"$v\n", $style);
            $v = sprintf('<span class=sf-dump-ellipsis>%s</span>%s', substr($v, 0, -strlen($label)), $label);
        }

        $v = "<span class=sf-dump-{$style}>".preg_replace_callback(static::$controlCharsRx, function ($c) use ($map) {
            $s = '<span class=sf-dump-default>';
            $c = $c[$i = 0];
            do {
                $s .= isset($map[$c[$i]]) ? $map[$c[$i]] : sprintf('\x%02X', ord($c[$i]));
            } while (isset($c[++$i]));

            return $s.'</span>';
        }, $v).'</span>';

        if (isset($attr['file']) && $href = $this->getSourceLink($attr['file'], isset($attr['line']) ? $attr['line'] : 0)) {
            $attr['href'] = $href;
        }
        if (isset($attr['href'])) {
            $v = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc($this->utf8Encode($attr['href'])), $v);
        }
        if (isset($attr['lang'])) {
            $v = sprintf('<code class="%s">%s</code>', esc($attr['lang']), $v);
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
        if ($this->headerIsDumped !== (null !== $this->outputStream ? $this->outputStream : $this->lineDumper)) {
            $this->line = $this->getDumpHeader().$this->line;
        }

        if (-1 === $depth) {
            $args = array('"'.$this->dumpId.'"');
            if ($this->extraDisplayOptions) {
                $args[] = json_encode($this->extraDisplayOptions, JSON_FORCE_OBJECT);
            }
            // Replace is for BC
            $this->line .= sprintf(str_replace('"%s"', '%s', $this->dumpSuffix), implode(', ', $args));
        }
        $this->lastDepth = $depth;

        $this->line = mb_convert_encoding($this->line, 'HTML-ENTITIES', 'UTF-8');

        if (-1 === $depth) {
            AbstractDumper::dumpLine(0);
        }
        AbstractDumper::dumpLine($depth);
    }

    private function getSourceLink($file, $line)
    {
        $options = $this->extraDisplayOptions + $this->displayOptions;

        if ($fmt = $options['fileLinkFormat']) {
            return is_string($fmt) ? strtr($fmt, array('%f' => $file, '%l' => $line)) : $fmt->format($file, $line);
        }

        return false;
    }
}

function esc($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
