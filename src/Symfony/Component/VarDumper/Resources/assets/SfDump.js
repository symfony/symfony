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
                e.preventDefault = function () {
                    e.returnValue = false;
                };
                e.target = e.srcElement;
                callback(e);
            });
        };
    }

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
                a = s.querySelectorAll('.' + oldClass);
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

    function collapseAll(root)
    {
        var a = root.querySelector('a.sf-dump-toggle');
        if (a) {
            collapse(a, true);
            expand(a);

            return true;
        }

        return false;
    }

    function reveal(node) {
        var current = node;
        var parents = [];
        var previous = null;
        var parent = null;

        while ((parent = current.parentNode || {}) && (previous = parent.previousSibling)) {
            current = current.parentNode;
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

    function highlight(root, activeNode, nodes)
    {
        resetHighlightedNodes(root);

        (nodes||[]).forEach(function (node) {
            if (!/\bsf-dump-highlight\b/.test(node.className)) {
                node.className = node.className + ' sf-dump-highlight';
            }
        });

        if (!/\bsf-dump-highlight-active\b/.test(activeNode.className)) {
            activeNode.className = activeNode.className + ' sf-dump-highlight-active';
        }
    }

    function resetHighlightedNodes(root)
    {
        root.querySelectorAll('.sf-dump-str, .sf-dump-key, .sf-dump-public, .sf-dump-protected, .sf-dump-private').forEach(function (strNode) {
            strNode.className = strNode.className.replace(/\b sf-dump-highlight\b/, '');
            strNode.className = strNode.className.replace(/\b sf-dump-highlight-active\b/, '');
        });
    }

    return function (root, x) {
        root = doc.getElementById(root);

        var indentRx = new RegExp('^(' + (root.getAttribute('data-indent-pad') || '  ').replace(rxEsc, '\\$1') + ')+', 'm'),
            options = "{$options}",
            elt = root.getElementsByTagName('A'),
            len = elt.length,
            i = 0, s, h,
            t = [];

        while (i < len) t.push(elt[i++]);

        for (i in x) {
            options[i] = x[i];
        }

        var delay = (function () {
            var timer = 0;
            return function (callback, ms) {
                clearTimeout(timer);
                timer = setTimeout(callback, ms);
            };
        })();

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
                    refStyle.innerHTML = 'pre.sf-dump .' + a[0] + '{background-color: #B729D9; color: #FFF !important; border-radius: 2px}';
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
                        r.innerHTML = r.innerHTML.replace(new RegExp('^' + f[0].replace(rxEsc, '\\$1'), 'mg'), t[0]);
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

        root.addEventListener('keydown', function (e) {
            if (114 === e.keyCode || (isCtrlKey(e) && 70 === e.keyCode)) {
                /* CTRL + F or CMD + F */
                e.preventDefault();
                if (!root.querySelector('.sf-dump-search-wrapper')) {
                    var search = document.createElement('div');
                    search.className = 'sf-dump-search-wrapper';
                    search.innerHTML = `
                        <input type="text" class="sf-dump-search-input">
                        <button type="button" class="sf-dump-search-input-previous" tabindex="-1">▲</button>
                        <button type="button" class="sf-dump-search-input-next" tabindex="-1">▼</button>
                        <span class="sf-dump-search-count">0 on 0</span>
                    `;
                    root.appendChild(search);

                    var state = new SearchState();
                    var searchInput = search.querySelector('.sf-dump-search-input');
                    var counter = search.querySelector('.sf-dump-search-count');

                    searchInput.addEventListener('keydown', function (e) {
                        /* Don't intercept escape key in order to not start a search */
                        if (27 === e.keyCode) {
                            return;
                        }

                        delay(function () {
                            state.reset();
                            collapseAll(root);
                            resetHighlightedNodes(root);
                            var searchQuery = e.target.value;
                            if ('' === searchQuery) {
                                counter.textContent = '0 on 0';

                                return;
                            }

                            var xpathResult = document.evaluate('//pre[@id="' + root.id + '"]//span[@class="sf-dump-str" or @class="sf-dump-key" or @class="sf-dump-public" or @class="sf-dump-protected" or @class="sf-dump-private"][contains(child::text(), \"' + searchQuery + '\")]', document, null, XPathResult.ORDERED_NODE_ITERATOR_TYPE);

                            while (node = xpathResult.iterateNext()) {
                                state.nodes.push(node);
                            }

                            var currentNode = state.current();
                            if (currentNode) {
                                reveal(currentNode);
                                highlight(root, currentNode, state.nodes);
                            }

                            counter.textContent = (state.isEmpty() ? 0 : state.idx + 1) + ' on ' + state.count();
                        }, 400);
                    });
                    search.querySelectorAll('.sf-dump-search-input-next, .sf-dump-search-input-previous').forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                            e.preventDefault();
                            var direction = -1 !== e.target.className.indexOf('next') ? 'next' : 'previous';
                            'next' === direction ? state.next() : state.previous();
                            searchInput.focus();
                            collapseAll(root);
                            var currentNode = state.current();
                            if (currentNode) {
                                reveal(currentNode);
                                highlight(root, currentNode, state.nodes);
                            }

                            counter.textContent = (state.isEmpty() ? 0 : state.idx + 1) + ' on ' + state.count();
                        })
                    });
                }

                root.querySelector('.sf-dump-search-input').focus();
            } else if (27 === e.keyCode) {
                /* ESC key */
                e.preventDefault();
                var search = root.querySelector('.sf-dump-search-wrapper');
                if (search) {
                    root.removeChild(search);
                }
                resetHighlightedNodes(root);
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
                a.title = (a.title ? a.title + '\n[' : '[') + keyHint + '+click] Expand all children';
                a.innerHTML += '<span>▼</span>';
                a.className += ' sf-dump-toggle';
                x = 1;
                if ('sf-dump' != elt.parentNode.className) {
                    x += elt.parentNode.getAttribute('data-depth') / 1;
                }
                elt.setAttribute('data-depth', x);
                if (x > options.maxDepth) {
                    toggle(a);
                }
            } else if ('sf-dump-ref' == elt.className && (a = elt.getAttribute('href'))) {
                a = a.substr(1);
                elt.className += ' ' + a;

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
                    elt.innerHTML = '<span class=sf-dump-str-collapse>' + h + '<a class="sf-dump-ref sf-dump-str-toggle" title="Collapse"> ◀</a></span>' +
                        '<span class=sf-dump-str-expand>' + elt.innerHTML + '<a class="sf-dump-ref sf-dump-str-toggle" title="' + x + ' remaining characters"> ▶</a></span>';
                }
            }
        } catch (e) {
        }
    };
})(document);
