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

export {addEventListener};
