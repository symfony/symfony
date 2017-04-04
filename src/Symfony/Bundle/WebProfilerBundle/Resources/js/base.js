window.Sfjs = window.Sfjs || (function() {
    "use strict";

    var classListIsSupported = 'classList' in document.documentElement;

    if (classListIsSupported) {
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

    var noop = function() {};

    var profilerStorageKey = 'sf2/profiler/';

    var request = function(url, onSuccess, onError, payload, options) {
        var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');
        options = options || {};
        options.maxTries = options.maxTries || 0;
        xhr.open(options.method || 'GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function(state) {
            if (4 !== xhr.readyState) {
                return null;
            }

            if (xhr.status == 404 && options.maxTries > 1) {
                setTimeout(function(){
                    options.maxTries--;
                    request(url, onSuccess, onError, payload, options);
                }, 500);

                return null;
            }

            if (200 === xhr.status) {
                (onSuccess || noop)(xhr);
            } else {
                (onError || noop)(xhr);
            }
        };
        xhr.send(payload || '');
    };

    var getPreference = function(name) {
        if (!window.localStorage) {
            return null;
        }

        return localStorage.getItem(profilerStorageKey + name);
    };

    var setPreference = function(name, value) {
        if (!window.localStorage) {
            return null;
        }

        localStorage.setItem(profilerStorageKey + name, value);
    };

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

    return {
        hasClass: hasClass,

        removeClass: removeClass,

        addClass: addClass,

        toggleClass: toggleClass,

        getPreference: getPreference,

        setPreference: setPreference,

        addEventListener: addEventListener,

        request: request,

        load: function(selector, url, onSuccess, onError, options) {
            var el = document.getElementById(selector);

            if (el && el.getAttribute('data-sfurl') !== url) {
                request(
                    url,
                    function(xhr) {
                        el.innerHTML = xhr.responseText;
                        el.setAttribute('data-sfurl', url);
                        removeClass(el, 'loading');
                        (onSuccess || noop)(xhr, el);
                    },
                    function(xhr) { (onError || noop)(xhr, el); },
                    '',
                    options
                );
            }

            return this;
        },

        toggle: function(selector, elOn, elOff) {
            var tmp = elOn.style.display,
                el = document.getElementById(selector);

            elOn.style.display = elOff.style.display;
            elOff.style.display = tmp;

            if (el) {
                el.style.display = 'none' === tmp ? 'none' : 'block';
            }

            return this;
        }
    };
})();
