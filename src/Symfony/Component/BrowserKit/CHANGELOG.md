CHANGELOG
=========

6.1
---

 * Add `toArray` method to `Response`

5.3
---

 * Added `jsonRequest` method to `AbstractBrowser`
 * Allowed sending a body with GET requests when a content-type is defined

5.2.0
-----

 * [BC BREAK] Request parameters are now casted to string in `Request::__construct()`.

4.3.0
-----

 * Added PHPUnit constraints: `BrowserCookieValueSame` and `BrowserHasCookie`
 * Added `HttpBrowser`, an implementation of a browser with the HttpClient component
 * Renamed `Client` to `AbstractBrowser`
 * Marked `Response` final.
 * Deprecated `Response::buildHeader()`
 * Deprecated `Response::getStatus()`, use `Response::getStatusCode()` instead

4.2.0
-----

 * The method `Client::submit()` will have a new `$serverParameters` argument
   in version 5.0, not defining it is deprecated
 * Added ability to read the "samesite" attribute of cookies using `Cookie::getSameSite()`

3.4.0
-----

 * [BC BREAK] Client will skip redirects during history navigation
   (back and forward calls) according to W3C Browsers recommendation

3.3.0
-----

 * [BC BREAK] The request method is dropped from POST to GET when the response
   status code is 301.

3.2.0
-----

 * Client HTTP user agent has been changed to 'Symfony BrowserKit'

2.3.0
-----

 * [BC BREAK] `Client::followRedirect()` won't redirect responses with
   a non-3xx Status Code and `Location` header anymore, as per
   http://tools.ietf.org/html/rfc2616#section-14.30

 * added `Client::getInternalRequest()` and `Client::getInternalResponse()` to
   have access to the BrowserKit internal request and response objects

2.1.0
-----

 * [BC BREAK] The CookieJar internals have changed to allow cookies with the
   same name on different sub-domains/sub-paths
