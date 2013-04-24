CHANGELOG
=========

2.3.0
-----

 * added `Client::getOriginRequest()` and `Client::getOriginResponse()` to
   have access to the origin request and response objects
 * [BC BREAK] The `Symfony\Component\HttpKernel\Client::getRequest()` method now
   returns a `Symfony\Component\BrowserKit\Request` instance
 * [BC BREAK] The `Symfony\Component\HttpKernel\Client::request()` method now
   always returns a `Symfony\Component\BrowserKit\Response` instance

2.1.0
-----

 * [BC BREAK] The CookieJar internals have changed to allow cookies with the
   same name on different sub-domains/sub-paths
