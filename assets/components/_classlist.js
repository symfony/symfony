let hasClass, removeClass, addClass, toggleClass;

if ('classList' in document.documentElement) {
    hasClass = function (el, cssClass) {
        return el.classList.contains(cssClass);
    };
    removeClass = function (el, cssClass) {
        el.classList.remove(cssClass);
    };
    addClass = function (el, cssClass) {
        el.classList.add(cssClass);
    };
    toggleClass = function (el, cssClass) {
        el.classList.toggle(cssClass);
    };
} else {
    hasClass = function (el, cssClass) {
        return el.className.match(new RegExp('\\b' + cssClass + '\\b'));
    };
    removeClass = function (el, cssClass) {
        el.className = el.className.replace(new RegExp('\\b' + cssClass + '\\b'), ' ');
    };
    addClass = function (el, cssClass) {
        if (!hasClass(el, cssClass)) {
            el.className += " " + cssClass;
        }
    };
    toggleClass = function (el, cssClass) {
        hasClass(el, cssClass) ? removeClass(el, cssClass) : addClass(el, cssClass);
    };
}

export {hasClass, removeClass, addClass, toggleClass};
