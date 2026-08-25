/* This file is based on WebProfilerBundle/Resources/views/Profiler/base_js.html.twig.
   If you make any change in this file, verify the same change is needed in the other file. */
/*<![CDATA[*/
(function() {
    "use strict";

    if ('classList' in document.documentElement) {
        var hasClass = function (el, cssClass) { return el.classList.contains(cssClass); };
        var removeClass = function(el, cssClass) { el.classList.remove(cssClass); };
        var addClass = function(el, cssClass) { el.classList.add(cssClass); };
        var toggleClass = function(el, cssClass) { el.classList.toggle(cssClass); };
    } else {
        var hasClass = function (el, cssClass) { return el.className.match(new RegExp('\\b' + cssClass + '\\b')); };
        var removeClass = function(el, cssClass) { el.className = el.className.replace(new RegExp('\\b' + cssClass + '\\b'), ' '); };
        var addClass = function(el, cssClass) { if (!hasClass(el, cssClass)) { el.className += " " + cssClass; } };
        var toggleClass = function(el, cssClass) { hasClass(el, cssClass) ? removeClass(el, cssClass) : addClass(el, cssClass); };
    }

    var addEventListener;

    var el = document.createElement('div');
    if (!('addEventListener' in el)) {
        addEventListener = function (element, eventName, callback) {
            element.attachEvent('on' + eventName, callback);
        };
    } else {
        addEventListener = function (element, eventName, callback) {
            element.addEventListener(eventName, callback, false);
        };
    }

    var announcer = document.querySelector('[data-announcer]');

    function announce(message) {
        if (!announcer) {
            return;
        }
        /* clearing first, then filling in a moment later, makes screen readers announce repeated copies */
        announcer.textContent = '';
        clearTimeout(announcer.sfAnnounceTimer);
        announcer.sfAnnounceTimer = setTimeout(function() {
            announcer.textContent = message;
            announcer.sfAnnounceTimer = setTimeout(function() { announcer.textContent = ''; }, 5000);
        }, 50);
    }

    if (navigator.clipboard) {
        document.querySelectorAll('[data-clipboard-text]:not([data-processed=true])').forEach(function(element) {
            removeClass(element, 'hidden');
            element.addEventListener('click', function(e) {
                /* Prevents from disallowing clicks on "copy to clipboard" elements inside toggles */
                e.stopPropagation();

                navigator.clipboard.writeText(element.getAttribute('data-clipboard-text')).then(function() {
                    announce('File path copied to clipboard');
                });
                /* briefly swap the copy glyph for a checkmark as feedback */
                addClass(element, 'is-copied');
                clearTimeout(element.sfCopiedTimer);
                element.sfCopiedTimer = setTimeout(function() { removeClass(element, 'is-copied'); }, 1500);
            })
            element.setAttribute('data-processed', 'true');
        });
    }

    (function createTabs() {
        function activate(controls, tab) {
            for (var k = 0; k < controls.length; k++) {
                var control = controls[k];
                var panel = document.getElementById(control.getAttribute('aria-controls'));
                var selected = control === tab;
                control.setAttribute('aria-selected', selected ? 'true' : 'false');
                if (selected) { control.removeAttribute('tabindex'); } else { control.setAttribute('tabindex', '-1'); }
                if (panel) { selected ? panel.removeAttribute('hidden') : panel.setAttribute('hidden', ''); }
            }
        }

        var tabGroups = document.querySelectorAll('.sf-tabs:not([data-processed=true])');
        for (var i = 0; i < tabGroups.length; i++) {
            (function (group) {
                /* only wire the tabs that belong to THIS group, so a nested .sf-tabs keeps its own tablist instead of being merged in */
                var controls = [];
                group.querySelectorAll('.tab-navigation [role=tab]').forEach(function(tab) {
                    if (tab.closest('.sf-tabs') === group) { controls.push(tab); }
                });
                if (!controls.length) {
                    /* no server-rendered tablist: leave the group unclaimed for the profiler's own tabs script */
                    return;
                }
                for (var j = 0; j < controls.length; j++) {
                    (function (control, index) {
                        control.addEventListener('click', function() { activate(controls, control); });
                        control.addEventListener('keydown', function(e) {
                            var next = null;
                            if ('ArrowRight' === e.key || 'ArrowDown' === e.key) { next = controls[(index + 1) % controls.length]; }
                            else if ('ArrowLeft' === e.key || 'ArrowUp' === e.key) { next = controls[(index - 1 + controls.length) % controls.length]; }
                            else if ('Home' === e.key) { next = controls[0]; }
                            else if ('End' === e.key) { next = controls[controls.length - 1]; }
                            if (next) { e.preventDefault(); activate(controls, next); next.focus(); }
                        });
                    })(controls[j], j);
                }

                group.setAttribute('data-processed', 'true');
            })(tabGroups[i]);
        }
    })();

    (function createExceptionChain() {
        /* selecting an exception in the chain expands it (collapsing the others) and shows its trace panel */
        var summaries = document.querySelectorAll('.exc-summary');
        if (!summaries.length) {
            return;
        }

        function select(index) {
            document.querySelectorAll('.exc-item').forEach(function(item) {
                var summary = item.querySelector('.exc-summary');
                var expanded = summary.getAttribute('data-exception-index') === index;
                expanded ? addClass(item, 'is-expanded') : removeClass(item, 'is-expanded');
                summary.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
            document.querySelectorAll('.exc-panel').forEach(function(panel) {
                panel.getAttribute('data-exception-index') === index ? addClass(panel, 'is-active') : removeClass(panel, 'is-active');
            });
        }

        summaries.forEach(function(summary) {
            summary.addEventListener('click', function() {
                select(summary.getAttribute('data-exception-index'));
            });
        });

        /* long chains hide their middle exceptions behind a "show more" control */
        var moreBtn = document.querySelector('.exc-more-btn');
        if (moreBtn) {
            moreBtn.addEventListener('click', function() {
                document.querySelectorAll('.exc-item-hidden').forEach(function(item) { removeClass(item, 'exc-item-hidden'); });
                moreBtn.closest('.exc-item-more').remove();
            });
        }
    })();

    (function createCopyAsText() {
        var buttons = [].slice.call(document.querySelectorAll('[data-copy-text]'));
        if (!buttons.length || !navigator.clipboard) {
            return; /* leave the buttons hidden when the Clipboard API is unavailable */
        }

        function textOf(el) { return el ? el.textContent.replace(/ /g, ' ').replace(/[ \t]+\n/g, '\n').trim() : ''; }

        /* rebuilds the Monolog-style one-liner from the DOM so copied logs read like Symfony's own log
           files; there is no `extra` in debug records, so it always ends with `[]` like LineFormatter */
        function logAsText(line) {
            var channel = line.getAttribute('data-channel');
            var message = line.querySelector('.log-message');
            var json = line.querySelector('pre.log-json');
            var context = '[]';
            if (json) {
                context = json.textContent;
                try {
                    /* the DOM carries the context pretty-printed; log files use the compact form */
                    context = JSON.stringify(JSON.parse(context));
                } catch (e) {
                }
            }

            return '[' + line.getAttribute('data-time') + '] '
                + (channel ? channel + '.' : '')
                + line.getAttribute('data-level').toUpperCase() + ': '
                + (message ? message.textContent : '') + ' ' + context + ' []';
        }

        function buildTextContent(btn) {
            /* each chained exception panel has its own copy button and stack trace */
            var panel = btn.closest('.exc-panel');
            var stack = textOf(panel ? panel.querySelector('pre.stacktrace') : document.querySelector('pre.stacktrace'));

            var logLines = [].slice.call(document.querySelectorAll('[data-logs] .log-line'));
            var logs = logLines.map(logAsText).join('\n').trim();

            var md = '';
            if (stack) { md += '### Stack trace\n\n```\n' + stack + '\n```\n'; }
            if (logs) { md += '\n### Logs\n\n```\n' + logs + '\n```\n'; }

            return md;
        }

        buttons.forEach(function(btn) {
            btn.removeAttribute('hidden');
            btn.addEventListener('click', function() {
                navigator.clipboard.writeText(buildTextContent(btn)).then(function() {
                    announce('Copied to clipboard');
                    var label = btn.querySelector('.copy-label');
                    if (!label) { return; }
                    var previous = label.textContent;
                    label.textContent = 'Copied!';
                    setTimeout(function() { label.textContent = previous; }, 1500);
                });
            });
        });
    })();

    (function createToggles() {
        var toggles = document.querySelectorAll('.sf-toggle:not([data-processed=true])');

        for (var i = 0; i < toggles.length; i++) {
            var elementSelector = toggles[i].getAttribute('data-toggle-selector');
            var element = document.querySelector(elementSelector);

            addClass(element, 'sf-toggle-content');

            if (toggles[i].hasAttribute('data-toggle-initial') && toggles[i].getAttribute('data-toggle-initial') == 'display') {
                addClass(toggles[i], 'sf-toggle-on');
                addClass(element, 'sf-toggle-visible');
            } else {
                addClass(toggles[i], 'sf-toggle-off');
                addClass(element, 'sf-toggle-hidden');
            }

            addEventListener(toggles[i], 'click', function(e) {
                var toggle = e.currentTarget;

                if (e.target.closest('a, .sf-toggle') !== toggle) {
                    return;
                }

                e.preventDefault();

                if ('' !== window.getSelection().toString()) {
                    /* Don't do anything on text selection */
                    return;
                }

                var element = document.querySelector(toggle.getAttribute('data-toggle-selector'));

                toggleClass(toggle, 'sf-toggle-on');
                toggleClass(toggle, 'sf-toggle-off');
                toggleClass(element, 'sf-toggle-hidden');
                toggleClass(element, 'sf-toggle-visible');

                /* the toggle doesn't change its contents when clicking on it */
                if (!toggle.hasAttribute('data-toggle-alt-content')) {
                    return;
                }

                if (!toggle.hasAttribute('data-toggle-original-content')) {
                    toggle.setAttribute('data-toggle-original-content', toggle.innerHTML);
                }

                var currentContent = toggle.innerHTML;
                var originalContent = toggle.getAttribute('data-toggle-original-content');
                var altContent = toggle.getAttribute('data-toggle-alt-content');
                toggle.innerHTML = currentContent !== altContent ? altContent : originalContent;
            });

            toggles[i].setAttribute('data-processed', 'true');
        }
    })();

    (function createVendorFrameToggle() {
        document.querySelectorAll('.trace-vendor-toggle').forEach(function(toggle) {
            var box = toggle.closest('.trace-details');
            if (!box) { return; }
            addEventListener(toggle, 'click', function() {
                toggleClass(box, 'trace-show-vendor');
                toggle.setAttribute('aria-expanded', hasClass(box, 'trace-show-vendor') ? 'true' : 'false');
            });
        });
    })();

    (function createRawTraceCopy() {
        if (!navigator.clipboard) {
            return; /* leave the button hidden when the Clipboard API is unavailable */
        }
        document.querySelectorAll('.trace-raw-copy').forEach(function(btn) {
            removeClass(btn, 'hidden');
            var card = btn.closest('.trace-raw-card');
            var pre = card ? card.querySelector('pre') : null;
            addEventListener(btn, 'click', function() {
                navigator.clipboard.writeText(pre ? pre.textContent.replace(/[ \t]+\n/g, '\n').trim() : '').then(function() {
                    announce('Stack trace copied to clipboard');
                });
                addClass(btn, 'is-copied');
                clearTimeout(btn.sfCopiedTimer);
                btn.sfCopiedTimer = setTimeout(function() { removeClass(btn, 'is-copied'); }, 1500);
            });
        });
    })();

    (function createLogs() {
        var root = document.querySelector('[data-logs]');
        if (!root) {
            return;
        }

        var allLines = [].slice.call(root.querySelectorAll('.log-line'));
        var lines = allLines.filter(function(line) { return hasClass(line, 'has-detail'); });

        function setExpanded(line, expanded) {
            if (!hasClass(line, 'has-detail')) {
                return; /* nothing to reveal for context-less rows */
            }
            expanded ? addClass(line, 'is-expanded') : removeClass(line, 'is-expanded');
            var summary = line.querySelector('.log-summary');
            if (summary) { summary.setAttribute('aria-expanded', expanded ? 'true' : 'false'); }
        }

        lines.forEach(function(line) {
            var summary = line.querySelector('.log-summary');
            if (summary) {
                addEventListener(summary, 'click', function() { setExpanded(line, !hasClass(line, 'is-expanded')); });
            }
        });

        var expandBtn = root.querySelector('.logs-expand-all');
        if (expandBtn) {
            addEventListener(expandBtn, 'click', function() {
                /* derive the action from the live state so it stays correct after manual toggles/search */
                var expand = lines.some(function(line) { return !hasClass(line, 'is-expanded'); });
                lines.forEach(function(line) { setExpanded(line, expand); });
                expandBtn.setAttribute('aria-expanded', expand ? 'true' : 'false');
            });
        }

        /* unified filtering: deep-search query AND per-column (level/channel) selections */
        var searchQuery = '';
        var columnFilters = {}; /* { level: {value:true,...}|null, channel: ... }; null = all selected */

        function matches(line) {
            if (searchQuery) {
                if (undefined === line.sfHaystack) { line.sfHaystack = line.textContent.toLowerCase(); }
                if (-1 === line.sfHaystack.indexOf(searchQuery)) { return false; }
            }
            for (var key in columnFilters) {
                var set = columnFilters[key];
                if (set && !set[line.getAttribute('data-' + key)]) { return false; }
            }
            return true;
        }

        var emptyState = root.querySelector('.logs-empty');
        function applyFilters() {
            var anyVisible = false;
            allLines.forEach(function(line) {
                var visible = matches(line);
                line.hidden = !visible;
                if (visible) {
                    anyVisible = true;
                    /* searching reveals matches; otherwise restore each row's default expanded state */
                    setExpanded(line, searchQuery ? true : '1' === line.getAttribute('data-default-expanded'));
                }
            });
            if (emptyState) { emptyState.hidden = anyVisible; }
        }

        var search = root.querySelector('.logs-search');
        if (search) {
            addEventListener(search, 'input', function() {
                searchQuery = search.value.trim().toLowerCase();
                applyFilters();
            });
        }

        var openFilter = null;
        function closeFilter() {
            if (openFilter) {
                openFilter.menu.hidden = true;
                openFilter.trigger.setAttribute('aria-expanded', 'false');
                openFilter = null;
            }
        }

        root.querySelectorAll('.logs-filter').forEach(function(filter) {
            var key = filter.getAttribute('data-filter');
            var trigger = filter.querySelector('.logs-filter-trigger');
            var menu = filter.querySelector('.logs-filter-menu');
            var boxes = [].slice.call(filter.querySelectorAll('input[type=checkbox]'));
            var allLink = filter.querySelector('.logs-filter-all');

            function recompute() {
                /* null-prototype object so channels named like Object.prototype members (e.g. "constructor") filter correctly */
                var selected = Object.create(null), count = 0;
                boxes.forEach(function(b) { if (b.checked) { selected[b.value] = true; ++count; } });
                var allOn = count === boxes.length;
                columnFilters[key] = allOn ? null : selected;
                allOn ? removeClass(filter, 'is-filtered') : addClass(filter, 'is-filtered');
                allLink.textContent = allOn ? 'Select none' : 'Select all';
                applyFilters();
            }

            addEventListener(trigger, 'click', function(e) {
                e.stopPropagation();
                var willOpen = 'true' !== trigger.getAttribute('aria-expanded');
                closeFilter();
                if (willOpen) {
                    menu.hidden = false;
                    trigger.setAttribute('aria-expanded', 'true');
                    openFilter = { menu: menu, trigger: trigger };
                }
            });
            addEventListener(menu, 'click', function(e) { e.stopPropagation(); });
            boxes.forEach(function(b) { addEventListener(b, 'change', recompute); });
            addEventListener(allLink, 'click', function() {
                /* if anything is unchecked, "Select all" checks everything; otherwise clear all */
                var anyOff = boxes.some(function(b) { return !b.checked; });
                boxes.forEach(function(b) { b.checked = anyOff; });
                recompute();
            });
        });

        addEventListener(document, 'click', closeFilter);
        addEventListener(document, 'keydown', function(e) {
            if ('Escape' !== e.key || !openFilter) {
                return;
            }
            /* hiding the menu drops the focus it holds, so hand it back to the trigger */
            var trigger = openFilter.trigger;
            var restoreFocus = openFilter.menu.contains(document.activeElement);
            closeFilter();
            if (restoreFocus) { trigger.focus(); }
        });
    })();
})();
/*]]>*/
