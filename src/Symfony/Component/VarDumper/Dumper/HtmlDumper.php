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
        'default' => 'background-color:#18171B; color:#FF8400; line-height:1.2em; font:12px Menlo, Monaco, Consolas, monospace; word-wrap: break-word; white-space: pre-wrap; position:relative; z-index:99999; word-break: break-all',
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

function collapse(a, recursive) {
    var s = a.nextSibling || {}, oldClass = s.className;

    if ('sf-dump-expanded' == oldClass) {
        toggle(a, recursive);

        return true;
    }

    return false;
};

function expand(a, recursive) {
    var s = a.nextSibling || {}, oldClass = s.className;

    if ('sf-dump-compact' == oldClass) {
        toggle(a, recursive);

        return true;
    }

    return false;
};

function collapseAll(root) {
    var a = root.querySelector('a.sf-dump-toggle');
    if (a) {
        collapse(a, true);
        expand(a);

        return true;
    }

    return false;
}

function reveal(node) {
    var previous, parents = [];

    while ((node = node.parentNode || {}) && (previous = node.previousSibling) && 'A' === previous.tagName) {
        parents.push(previous);
    }

    if (0 !== parents.length) {
        parents.forEach(function (parent) {
            expand(parent);
        });

        return true;
    }

    return false;
}

function highlight(root, activeNode, nodes) {
    resetHighlightedNodes(root);

    Array.from(nodes||[]).forEach(function (node) {
        if (!/\bsf-dump-highlight\b/.test(node.className)) {
            node.className = node.className + ' sf-dump-highlight';
        }
    });

    if (!/\bsf-dump-highlight-active\b/.test(activeNode.className)) {
        activeNode.className = activeNode.className + ' sf-dump-highlight-active';
    }
}

function resetHighlightedNodes(root) {
    Array.from(root.querySelectorAll('.sf-dump-str, .sf-dump-key, .sf-dump-public, .sf-dump-protected, .sf-dump-private')).forEach(function (strNode) {
        strNode.className = strNode.className.replace(/\b sf-dump-highlight\b/, '');
        strNode.className = strNode.className.replace(/\b sf-dump-highlight-active\b/, '');
    });
}

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
    function xpathString(str) {
        var parts = str.match(/[^'"]+|['"]/g).map(function (part) {
            if ("'" == part)  {
                return '"\'"';
            }
            if ('"' == part) {
                return "'\"'";
            }

            return "'" + part + "'";
        });

        return "concat(" + parts.join(",") + ", '')";
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

    if (doc.evaluate && Array.from && root.children.length > 1) {
        root.setAttribute('tabindex', 0);

        SearchState = function () {
            this.nodes = [];
            this.idx = 0;
        };
        SearchState.prototype = {
            next: function () {
                if (this.isEmpty()) {
                    return this.current();
                }
                this.idx = this.idx < (this.nodes.length - 1) ? this.idx + 1 : this.idx;
        
                return this.current();
            },
            previous: function () {
                if (this.isEmpty()) {
                    return this.current();
                }
                this.idx = this.idx > 0 ? this.idx - 1 : this.idx;
        
                return this.current();
            },
            isEmpty: function () {
                return 0 === this.count();
            },
            current: function () {
                if (this.isEmpty()) {
                    return null;
                }
                return this.nodes[this.idx];
            },
            reset: function () {
                this.nodes = [];
                this.idx = 0;
            },
            count: function () {
                return this.nodes.length;
            },
        };

        function showCurrent(state)
        {
            var currentNode = state.current();
            if (currentNode) {
                reveal(currentNode);
                highlight(root, currentNode, state.nodes);
            }
            counter.textContent = (state.isEmpty() ? 0 : state.idx + 1) + ' of ' + state.count();
        }

        var search = doc.createElement('div');
        search.className = 'sf-dump-search-wrapper sf-dump-search-hidden';
        search.innerHTML = '
            <input type="text" class="sf-dump-search-input">
            <span class="sf-dump-search-count">0 of 0<\/span>
            <button type="button" class="sf-dump-search-input-previous" tabindex="-1">
                <svg viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1683 1331l-166 165q-19 19-45 19t-45-19l-531-531-531 531q-19 19-45 19t-45-19l-166-165q-19-19-19-45.5t19-45.5l742-741q19-19 45-19t45 19l742 741q19 19 19 45.5t-19 45.5z"\/>
                <\/svg>
            <\/button>
            <button type="button" class="sf-dump-search-input-next" tabindex="-1">
                <svg viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1683 808l-742 741q-19 19-45 19t-45-19l-742-741q-19-19-19-45.5t19-45.5l166-165q19-19 45-19t45 19l531 531 531-531q19-19 45-19t45 19l166 165q19 19 19 45.5t-19 45.5z"\/>
                <\/svg>
            <\/button>
        ';
        root.insertBefore(search, root.firstChild);

        var state = new SearchState();
        var searchInput = search.querySelector('.sf-dump-search-input');
        var counter = search.querySelector('.sf-dump-search-count');
        var searchInputTimer = 0;
        var previousSearchQuery = '';

        addEventListener(searchInput, 'keyup', function (e) {
            var searchQuery = e.target.value;
            /* Don't perform anything if the pressed key didn't change the query */
            if (searchQuery === previousSearchQuery) {
                return;
            }
            previousSearchQuery = searchQuery;
            clearTimeout(searchInputTimer);
            searchInputTimer = setTimeout(function () {
                state.reset();
                collapseAll(root);
                resetHighlightedNodes(root);
                if ('' === searchQuery) {
                    counter.textContent = '0 of 0';

                    return;
                }

                var xpathResult = doc.evaluate('//pre[@id="' + root.id + '"]//span[@class="sf-dump-str" or @class="sf-dump-key" or @class="sf-dump-public" or @class="sf-dump-protected" or @class="sf-dump-private"][contains(child::text(), ' + xpathString(searchQuery) + ')]', document, null, XPathResult.ORDERED_NODE_ITERATOR_TYPE, null);

                while (node = xpathResult.iterateNext()) state.nodes.push(node);
                
                showCurrent(state);
            }, 400);
        });

        Array.from(search.querySelectorAll('.sf-dump-search-input-next, .sf-dump-search-input-previous')).forEach(function (btn) {
            addEventListener(btn, 'click', function (e) {
                e.preventDefault();
                var direction = -1 !== e.target.className.indexOf('next') ? 'next' : 'previous';
                'next' === direction ? state.next() : state.previous();
                searchInput.focus();
                collapseAll(root);
                showCurrent(state);
            })
        });

        addEventListener(root, 'keydown', function (e) {
            var isSearchActive = !/\bsf-dump-search-hidden\b/.test(search.className);
            if ((114 === e.keyCode && !isSearchActive) || (isCtrlKey(e) && 70 === e.keyCode)) {
                /* F3 or CMD/CTRL + F */
                e.preventDefault();
                search.className = search.className.replace(/\bsf-dump-search-hidden\b/, '');
                searchInput.focus();
            } else if (isSearchActive) {
                if (27 === e.keyCode) {
                    /* ESC key */
                    search.className += ' sf-dump-search-hidden';
                    e.preventDefault();
                    resetHighlightedNodes(root);
                    searchInput.value = '';
                } else if (
                    (isCtrlKey(e) && 71 === e.keyCode) /* CMD/CTRL + G */
                    || 13 === e.keyCode /* Enter */
                    || 114 === e.keyCode /* F3 */
                ) {
                    e.preventDefault();
                    e.shiftKey ? state.previous() : state.next();
                    collapseAll(root);
                    showCurrent(state);
                }
            }
        });
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
pre.sf-dump:after {
   content: "";
   visibility: hidden;
   display: block;
   height: 0;
   clear: both;
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
pre.sf-dump .sf-dump-ellipsis+.sf-dump-ellipsis {
    max-width: none;
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
.sf-dump-public.sf-dump-highlight,
.sf-dump-protected.sf-dump-highlight,
.sf-dump-private.sf-dump-highlight,
.sf-dump-str.sf-dump-highlight,
.sf-dump-key.sf-dump-highlight {
    background: rgba(111, 172, 204, 0.3);
    border: 1px solid #7DA0B1;
    border-radius: 3px;
}
.sf-dump-public.sf-dump-highlight-active,
.sf-dump-protected.sf-dump-highlight-active,
.sf-dump-private.sf-dump-highlight-active,
.sf-dump-str.sf-dump-highlight-active,
.sf-dump-key.sf-dump-highlight-active {
    background: rgba(253, 175, 0, 0.4);
    border: 1px solid #ffa500;
    border-radius: 3px;
}
pre.sf-dump .sf-dump-search-hidden {
    display: none;
}
pre.sf-dump .sf-dump-search-wrapper {
    float: right;
    font-size: 0;
    white-space: nowrap;
    max-width: 100%;
    text-align: right;
}
pre.sf-dump .sf-dump-search-wrapper > * {
    vertical-align: top;
    box-sizing: border-box;
    height: 21px;
    font-weight: normal;
    border-radius: 0;
    background: #FFF;
    color: #757575;
    border: 1px solid #BBB;
}
pre.sf-dump .sf-dump-search-wrapper > input.sf-dump-search-input {
    padding: 3px;
    height: 21px;
    font-size: 12px;
    border-right: none;
    width: 140px;
    border-top-left-radius: 3px;
    border-bottom-left-radius: 3px;
    color: #000;
}
pre.sf-dump .sf-dump-search-wrapper > .sf-dump-search-input-next,
pre.sf-dump .sf-dump-search-wrapper > .sf-dump-search-input-previous {
    background: #F2F2F2;
    outline: none;
    border-left: none;
    font-size: 0;
    line-height: 0;
}
pre.sf-dump .sf-dump-search-wrapper > .sf-dump-search-input-next {
    border-top-right-radius: 3px;
    border-bottom-right-radius: 3px;
}
pre.sf-dump .sf-dump-search-wrapper > .sf-dump-search-input-next > svg,
pre.sf-dump .sf-dump-search-wrapper > .sf-dump-search-input-previous > svg {
    pointer-events: none;
    width: 12px;
    height: 12px;
}
pre.sf-dump .sf-dump-search-wrapper > .sf-dump-search-count {
    display: inline-block;
    padding: 0 5px;
    margin: 0;
    border-left: none;
    line-height: 21px;
    font-size: 12px;
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
            $class = 'sf-dump-ellipsis';
            if (isset($attr['ellipsis-type'])) {
                $class = sprintf('"%s sf-dump-ellipsis-%s"', $class, $attr['ellipsis-type']);
            }
            $label = esc(substr($value, -$attr['ellipsis']));
            $style = str_replace(' title="', " title=\"$v\n", $style);
            $v = sprintf('<span class=%s>%s</span>', $class, substr($v, 0, -strlen($label)));

            if (!empty($attr['ellipsis-tail'])) {
                $tail = strlen(esc(substr($value, -$attr['ellipsis'], $attr['ellipsis-tail'])));
                $v .= sprintf('<span class=sf-dump-ellipsis>%s</span>%s', substr($label, 0, $tail), substr($label, $tail));
            } else {
                $v .= $label;
            }
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
