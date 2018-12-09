let addEventListener;

if (!('addEventListener' in document.createElement('div'))) {
    addEventListener = function (element, eventName, callback) {
        element.attachEvent('on' + eventName, callback);
    };
} else {
    addEventListener = function (element, eventName, callback) {
        element.addEventListener(eventName, callback, false);
    };
}

function ready(callback) {
    addEventListener(document, 'DOMContentLoaded', callback);
}

function click(el, callback) {
    addEventListener(el, 'click', callback);
}

export {addEventListener, click, ready};
