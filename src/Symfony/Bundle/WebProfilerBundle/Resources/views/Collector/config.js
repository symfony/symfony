'use strict';


class InterceptRedirectCookie {
  /**
   * @param {string} cookieName
   */
  constructor(cookieName) {
    this.cookieName = cookieName;
  }

  /**
   * @return {boolean|null}
   */
  getValue() {
    const cookies = decodeURIComponent(document.cookie).split('; ');

    for (const cookie of cookies) {
      const cookieParts = cookie.split('=');

      if (cookieParts[0] === this.cookieName) {
        return cookieParts[1];
      }
    }

    return null;
  }

  /**
   * @param {boolean} enabled
   */
  set(enabled) {
    document.cookie = this.cookieName + "=" + enabled;
  }

  clear() {
    document.cookie = this.cookieName + "=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;";
  }
}


/**
 * @param {Element} checkbox
 * @param {InterceptRedirectCookie} cookie
 */
class InterceptRedirects {
  constructor(checkbox, cookie) {
    this.checkbox = checkbox;
    this.cookie = cookie;

    this.checkbox.checked = this.isInterceptRedirectsEnabled();
    this.checkbox.addEventListener('change', function onChange(e) {
      this.enableIntercept(e.target.checked);
    }.bind(this), false);
  }

  isInterceptRedirectsEnabled() {
    switch (this.cookie.getValue()) {
      case null:
      case 'no':
        return false;
      case 'yes':
        return true;
      default:
        this.cookie.clear();
        return false;
    }
  }

  enableIntercept(enabled) {
    this.cookie.set(enabled ? 'yes' : 'no');
    this.checkbox.checked = enabled;
  }
}
