import './tabs.css';
import {hasClass, addClass, removeClass, show, hide, toggle} from './_classlist';
import {click} from './_event';

export default function() {
    var tabGroups = document.querySelectorAll('.sf-tabs:not([data-processed=true])');

    // create the tab navigation for each group of tabs
    for (var i = 0; i < tabGroups.length; i++) {
        var tabs = tabGroups[i].querySelectorAll('.tab');
        var tabNavigation = document.createElement('ul');
        addClass(tabNavigation, 'tab-navigation');

        var selectedTabId = 'tab-' + i + '-0'; // select the first tab by default
        for (var j = 0; j < tabs.length; j++) {
            var tabId = 'tab-' + i + '-' + j;
            var tabTitle = tabs[j].querySelector('.tab-title').innerHTML;

            var tabNavigationItem = document.createElement('li');
            tabNavigationItem.setAttribute('data-tab-id', tabId);
            if (hasClass(tabs[j], 'active')) {
                selectedTabId = tabId;
            }
            if (hasClass(tabs[j], 'disabled')) {
                addClass(tabNavigationItem, 'disabled');
            }
            tabNavigationItem.innerHTML = tabTitle;
            tabNavigation.appendChild(tabNavigationItem);

            var tabContent = tabs[j].querySelector('.tab-content');
            tabContent.parentElement.setAttribute('id', tabId);
        }

        tabGroups[i].insertBefore(tabNavigation, tabGroups[i].firstChild);
        addClass(document.querySelector('[data-tab-id="' + selectedTabId + '"]'), 'active');
    }

    // display the active tab and add the 'click' event listeners
    for (i = 0; i < tabGroups.length; i++) {
        tabNavigation = tabGroups[i].querySelectorAll('.tab-navigation li');

        for (j = 0; j < tabNavigation.length; j++) {
            tabId = tabNavigation[j].getAttribute('data-tab-id');
            hide(document.getElementById(tabId).querySelector('.tab-title'));
            toggle(document.getElementById(tabId), hasClass(tabNavigation[j], 'active'));

            click(tabNavigation[j], function(e) {
                var activeTab = e.target || e.srcElement;

                // needed because when the tab contains HTML contents, user can click
                // on any of those elements instead of their parent '<li>' element
                while (activeTab.tagName.toLowerCase() !== 'li') {
                    activeTab = activeTab.parentNode;
                }

                // get the full list of tabs through the parent of the active tab element
                var tabNavigation = activeTab.parentNode.children;
                for (var k = 0; k < tabNavigation.length; k++) {
                    hide(document.getElementById(tabNavigation[k].getAttribute('data-tab-id')));
                    removeClass(tabNavigation[k], 'active');
                }

                addClass(activeTab, 'active');
                show(document.getElementById(activeTab.getAttribute('data-tab-id')));
            });
        }

        tabGroups[i].setAttribute('data-processed', 'true');
    }
}
