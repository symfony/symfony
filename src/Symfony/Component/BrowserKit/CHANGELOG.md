CHANGELOG
=========

2.1.0
-----

 * [BR BREAK] The Symfony\Component\HttpKernel\Client::request() method
   now returns a Symfony\Component\BrowserKit\Response instance
   (instead of a Symfony\Component\HttpFoundation\Response instance)

 * [BC BREAK] The CookieJar internals have changed to allow cookies with the
   same name on different sub-domains/sub-paths
