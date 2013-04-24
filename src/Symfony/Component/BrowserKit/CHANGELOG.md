CHANGELOG
=========

2.3.0
-----

 * added `Client::getInternalRequest()` and `Client::getInternalResponse()` to
   have access to the BrowserKit internal request and response objects
 * [BC BREAK] The `Symfony\Component\HttpKernel\Client::getRequest()` method now
   returns the request instance created by the client
 * [BC BREAK] The `Symfony\Component\HttpKernel\Client::request()` method now
   always returns the response instance created by the client

2.1.0
-----

 * [BC BREAK] The CookieJar internals have changed to allow cookies with the
   same name on different sub-domains/sub-paths
