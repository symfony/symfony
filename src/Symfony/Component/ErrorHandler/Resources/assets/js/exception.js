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

    if (navigator.clipboard) {
        document.querySelectorAll('[data-clipboard-text]').forEach(function(element) {
            removeClass(element, 'hidden');
            element.addEventListener('click', function() {
                navigator.clipboard.writeText(element.getAttribute('data-clipboard-text'));
            })
        });
    }

    (function createTabs() {
        /* the accessibility options of this component have been defined according to: */
        /* www.w3.org/WAI/ARIA/apg/example-index/tabs/tabs-manual.html */
        var tabGroups = document.querySelectorAll('.sf-tabs:not([data-processed=true])');

        /* create the tab navigation for each group of tabs */
        for (var i = 0; i < tabGroups.length; i++) {
            var tabs = tabGroups[i].querySelectorAll(':scope > .tab');
            var tabNavigation = document.createElement('div');
            tabNavigation.className = 'tab-navigation';
            tabNavigation.setAttribute('role', 'tablist');

            var selectedTabId = null;
            var firstEnabledTabId = null;
            for (var j = 0; j < tabs.length; j++) {
                var tabId = 'tab-' + i + '-' + j;
                var tabTitle = tabs[j].querySelector('.tab-title').innerHTML;

                var tabNavigationItem = document.createElement('button');
                addClass(tabNavigationItem, 'tab-control');
                tabNavigationItem.setAttribute('data-tab-id', tabId);
                tabNavigationItem.setAttribute('role', 'tab');
                tabNavigationItem.setAttribute('aria-controls', tabId);
                if (hasClass(tabs[j], 'disabled')) {
                    tabNavigationItem.disabled = true;
                } else if (null === firstEnabledTabId) {
                    firstEnabledTabId = tabId;
                }
                if (hasClass(tabs[j], 'active')) { selectedTabId = tabId; }
                tabNavigationItem.innerHTML = tabTitle;
                tabNavigation.appendChild(tabNavigationItem);

                var tabContent = tabs[j].querySelector('.tab-content');
                tabContent.parentElement.setAttribute('id', tabId);
            }

            tabGroups[i].insertBefore(tabNavigation, tabGroups[i].firstChild);
            /* fall back to the first enabled tab (not necessarily the first tab) when none */
            /* is explicitly marked active, so the default selection is never a disabled tab */
            addClass(document.querySelector('[data-tab-id="' + (selectedTabId || firstEnabledTabId || ('tab-' + i + '-0')) + '"]'), 'active');
        }

        /* activates a tab: shows its panel and hides the others in the same group */
        var activateTab = function(tab) {
            var tabsOfGroup = tab.parentNode.children;
            for (var i = 0; i < tabsOfGroup.length; i++) {
                var tabId = tabsOfGroup[i].getAttribute('data-tab-id');
                document.getElementById(tabId).className = 'hidden';
                removeClass(tabsOfGroup[i], 'active');
                tabsOfGroup[i].removeAttribute('aria-selected');
                tabsOfGroup[i].setAttribute('tabindex', '-1');
            }

            addClass(tab, 'active');
            tab.setAttribute('aria-selected', 'true');
            tab.removeAttribute('tabindex');
            var activeTabId = tab.getAttribute('data-tab-id');
            document.getElementById(activeTabId).className = 'block';
        };

        /* display the active tab and add the 'click' event listeners */
        for (i = 0; i < tabGroups.length; i++) {
            tabNavigation = tabGroups[i].querySelectorAll(':scope > .tab-navigation .tab-control');

            for (j = 0; j < tabNavigation.length; j++) {
                tabId = tabNavigation[j].getAttribute('data-tab-id');
                var tabPanel = document.getElementById(tabId);
                var tabPanelTitle = tabPanel.querySelector('.tab-title');
                tabPanel.setAttribute('role', 'tabpanel');
                tabPanel.setAttribute('tabindex', '0');
                tabPanelTitle.id = tabId + '-label';
                tabPanel.setAttribute('aria-labelledby', tabPanelTitle.id);
                addClass(tabPanelTitle, 'hidden');

                if (hasClass(tabNavigation[j], 'active')) {
                    tabPanel.className = 'block';
                    tabNavigation[j].setAttribute('aria-selected', 'true');
                    tabNavigation[j].removeAttribute('tabindex');
                } else {
                    tabPanel.className = 'hidden';
                    tabNavigation[j].removeAttribute('aria-selected');
                    tabNavigation[j].setAttribute('tabindex', '-1');
                }

                tabNavigation[j].addEventListener('click', function(e) {
                    var activeTab = e.target || e.srcElement;

                    /* needed because when the tab contains HTML contents, user can click */
                    /* on any of those elements instead of their parent '<button>' element */
                    while (activeTab.tagName.toLowerCase() !== 'button') {
                        activeTab = activeTab.parentNode;
                    }

                    activateTab(activeTab);
                });

                /* left/right arrow keys move focus to the previous/next tab and activate it */
                /* (www.w3.org/WAI/ARIA/apg/patterns/tabs/#keyboardinteraction) */
                tabNavigation[j].addEventListener('keydown', function(e) {
                    var key = e.key;
                    if ('ArrowLeft' !== key && 'ArrowRight' !== key) {
                        return;
                    }

                    e.preventDefault();

                    var tabsOfGroup = Array.prototype.slice.call(this.parentNode.children);
                    var currentIndex = tabsOfGroup.indexOf(this);
                    var newIndex = currentIndex;
                    /* skip disabled tabs, without looping forever if they all are */
                    do {
                        newIndex = (newIndex + ('ArrowRight' === key ? 1 : -1) + tabsOfGroup.length) % tabsOfGroup.length;
                    } while (tabsOfGroup[newIndex].disabled && newIndex !== currentIndex);

                    var newTab = tabsOfGroup[newIndex];
                    activateTab(newTab);
                    newTab.focus();
                });
            }

            tabGroups[i].setAttribute('data-processed', 'true');
        }
    })();

    (function createToggles() {
        var toggles = document.querySelectorAll('.sf-toggle:not([data-processed=true])');

        for (var i = 0; i < toggles.length; i++) {
            var elementSelector = toggles[i].getAttribute('data-toggle-selector');
            var element = document.querySelector(elementSelector);

            addClass(element, 'sf-toggle-content');

            /* wrap just the icons in a real <button>: the row itself can contain a link, and a */
            /* <button> can't contain one; inserted in place so nothing shifts visually */
            var iconClose = toggles[i].querySelector('.icon-close');
            var iconOpen = toggles[i].querySelector('.icon-open');
            var toggleButton = document.createElement('button');
            toggleButton.type = 'button';
            addClass(toggleButton, 'sf-toggle-button');
            var firstIcon = iconClose || iconOpen;
            if (firstIcon) {
                firstIcon.parentNode.insertBefore(toggleButton, firstIcon);
            }
            if (iconClose) {
                toggleButton.appendChild(iconClose);
            }
            if (iconOpen) {
                toggleButton.appendChild(iconOpen);
            }
            /* fixed label: the toggle's own text is read again right after as normal content, */
            /* so reusing it as the button's name (e.g. via aria-labelledby) would repeat it twice */
            toggleButton.setAttribute('aria-label', toggles[i].getAttribute('data-toggle-label') || 'Toggle details');
            toggleButton.setAttribute('aria-controls', element.id);

            if (toggles[i].hasAttribute('data-toggle-initial') && toggles[i].getAttribute('data-toggle-initial') == 'display') {
                addClass(toggles[i], 'sf-toggle-on');
                addClass(element, 'sf-toggle-visible');
                toggleButton.setAttribute('aria-expanded', 'true');
            } else {
                addClass(toggles[i], 'sf-toggle-off');
                addClass(element, 'sf-toggle-hidden');
                toggleButton.setAttribute('aria-expanded', 'false');
            }

            addEventListener(toggles[i], 'click', function(e) {
                var toggle = e.currentTarget;

                if (e.target.closest('a, span[data-clipboard-text], .sf-toggle') !== toggle) {
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
                toggle.querySelector('.sf-toggle-button').setAttribute('aria-expanded', hasClass(toggle, 'sf-toggle-on') ? 'true' : 'false');

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

    (function createFilters() {
        document.querySelectorAll('[data-filters] [data-filter]').forEach(function (filter) {
            var filters = filter.closest('[data-filters]'),
                type = 'choice',
                name = filter.dataset.filter,
                ucName = name.charAt(0).toUpperCase()+name.slice(1),
                list = document.createElement('ul'),
                values = filters.dataset['filter'+ucName] || filters.querySelectorAll('[data-filter-'+name+']'),
                labels = {},
                defaults = null,
                indexed = {},
                processed = {};
            if (typeof values === 'string') {
                type = 'level';
                labels = values.split(',');
                values = values.toLowerCase().split(',');
                defaults = values.length - 1;
            }
            addClass(list, 'filter-list');
            addClass(list, 'filter-list-'+type);
            list.setAttribute('role', 'menu');
            list.setAttribute('aria-label', 'Filter by ' + name);
            values.forEach(function (value, i) {
                if (value instanceof HTMLElement) {
                    value = value.dataset['filter'+ucName];
                }
                if (value in processed) {
                    return;
                }
                var option = document.createElement('li'),
                    label = i in labels ? labels[i] : value,
                    active = false,
                    matches;
                /* a real checkbox so Space toggles it and its checked state is exposed natively, */
                /* no custom JS needed; the <li> itself is just a transparent wrapper */
                option.setAttribute('role', 'none');
                var checkboxId = 'filter-option-' + name + '-' + i;
                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = checkboxId;
                checkbox.setAttribute('role', 'menuitemcheckbox');
                /* roving tabindex (APG menu pattern): only the first item is tabbable, the rest */
                /* are reached with the up/down arrows below */
                checkbox.setAttribute('tabindex', 0 === i ? '0' : '-1');
                var optionLabel = document.createElement('label');
                optionLabel.setAttribute('for', checkboxId);
                if ('' === label) {
                    optionLabel.innerHTML = '<em>(none)</em>';
                } else {
                    optionLabel.innerText = label;
                }
                option.dataset.filter = value;
                /* set here, not on the (role="none") <li>, so it reaches assistive technologies too */
                checkbox.setAttribute('title', 1 === (matches = filters.querySelectorAll('[data-filter-'+name+'="'+value+'"]').length) ? 'Matches 1 row' : 'Matches '+matches+' rows');
                option.appendChild(checkbox);
                option.appendChild(optionLabel);
                indexed[value] = i;
                list.appendChild(option);
                addEventListener(checkbox, 'keydown', function (e) {
                    if ('ArrowUp' !== e.key && 'ArrowDown' !== e.key) {
                        return;
                    }

                    e.preventDefault();

                    var checkboxesOfList = Array.prototype.slice.call(list.querySelectorAll('input[type=checkbox]'));
                    var currentIndex = checkboxesOfList.indexOf(checkbox);
                    var newIndex = currentIndex + ('ArrowDown' === e.key ? 1 : -1);
                    newIndex = (newIndex + checkboxesOfList.length) % checkboxesOfList.length;

                    checkbox.setAttribute('tabindex', '-1');
                    checkboxesOfList[newIndex].setAttribute('tabindex', '0');
                    checkboxesOfList[newIndex].focus();
                });
                addEventListener(checkbox, 'change', function () {
                    if ('choice' === type) {
                        filters.querySelectorAll('[data-filter-'+name+']').forEach(function (row) {
                            if (option.dataset.filter === row.dataset['filter'+ucName]) {
                                toggleClass(row, 'filter-hidden-'+name);
                            }
                        });
                        if (checkbox.checked) {
                            addClass(option, 'active');
                        } else {
                            removeClass(option, 'active');
                        }
                    } else if ('level' === type) {
                        /* re-clicking the current threshold must not uncheck it; use "last-active" */
                        /* (untouched by the native toggle) rather than checkbox.checked to detect that */
                        if (hasClass(option, 'last-active')) {
                            checkbox.checked = true;
                            return;
                        }
                        list.querySelectorAll('li').forEach(function (currentOption, j) {
                            var currentCheckbox = currentOption.querySelector('input[type=checkbox]');
                            if (j <= i) {
                                addClass(currentOption, 'active');
                                currentCheckbox.checked = true;
                                if (i === j) {
                                    addClass(currentOption, 'last-active');
                                } else {
                                    removeClass(currentOption, 'last-active');
                                }
                            } else {
                                removeClass(currentOption, 'active');
                                removeClass(currentOption, 'last-active');
                                currentCheckbox.checked = false;
                            }
                        });
                        filters.querySelectorAll('[data-filter-'+name+']').forEach(function (row) {
                            if (i < indexed[row.dataset['filter'+ucName]]) {
                                addClass(row, 'filter-hidden-'+name);
                            } else {
                                removeClass(row, 'filter-hidden-'+name);
                            }
                        });
                    }
                });
                if ('choice' === type) {
                    active = null === defaults || 0 <= defaults.indexOf(value);
                } else if ('level' === type) {
                    active = i <= defaults;
                    if (active && i === defaults) {
                        addClass(option, 'last-active');
                    }
                }
                checkbox.checked = active;
                if (active) {
                    addClass(option, 'active');
                } else {
                    filters.querySelectorAll('[data-filter-'+name+'="'+value+'"]').forEach(function (row) {
                        toggleClass(row, 'filter-hidden-'+name);
                    });
                }
                processed[value] = true;
            });

            if (1 < list.childNodes.length) {
                /* lets keyboard and screen reader users open the list; mouse users keep doing */
                /* it by hovering, as before (see :hover in the CSS) */
                var toggle = document.createElement('button');
                toggle.type = 'button';
                addClass(toggle, 'filter-toggle');
                toggle.innerHTML = filter.innerHTML;
                toggle.setAttribute('aria-haspopup', 'menu');
                toggle.setAttribute('aria-expanded', 'false');
                /* the list's <li> are hidden by CSS while closed, but the <ul role="menu"> itself */
                /* isn't; without this, screen readers can stumble on it while closed (e.g. when a */
                /* whole hidden tabpanel becomes visible at once) */
                list.setAttribute('aria-hidden', 'true');

                var closeFilter = function () {
                    removeClass(filter, 'filter-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    list.setAttribute('aria-hidden', 'true');
                };

                addEventListener(toggle, 'click', function () {
                    if (hasClass(filter, 'filter-open')) {
                        closeFilter();
                    } else {
                        addClass(filter, 'filter-open');
                        toggle.setAttribute('aria-expanded', 'true');
                        list.removeAttribute('aria-hidden');
                        /* opening a menu moves focus to the item with tabindex 0 (kept in sync by */
                        /* the arrow keys below), not left on the button (APG menu-button pattern) */
                        var firstItem = list.querySelector('[tabindex="0"]');
                        if (firstItem) {
                            firstItem.focus();
                        }
                    }
                });

                addEventListener(filter, 'keydown', function (e) {
                    if ('Escape' === e.key && hasClass(filter, 'filter-open')) {
                        closeFilter();
                        toggle.focus();
                    }
                });

                addEventListener(document, 'click', function (e) {
                    if (hasClass(filter, 'filter-open') && !filter.contains(e.target)) {
                        closeFilter();
                    }
                });

                /* closes on focus leaving rather than the mouse leaving: once opened by click or */
                /* keyboard, .filter-open keeps it open regardless of the mouse. This also closes it */
                /* when focus moves straight to another filter's toggle */
                addEventListener(filter, 'focusout', function (e) {
                    if (hasClass(filter, 'filter-open') && !filter.contains(e.relatedTarget)) {
                        closeFilter();
                    }
                });

                filter.innerHTML = '';
                filter.appendChild(toggle);
                filter.appendChild(list);
                filter.dataset.filtered = '';
            }
        });
    })();
})();
/*]]>*/
