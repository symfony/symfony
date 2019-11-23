CHANGELOG
=========

* 1.3.0 (2019-11-26)

  * Added support for streamed requests
  * Added support for Symfony 5.0+
  * Fixed bridging UploadedFile objects
  * Bumped minimum version of Symfony to 4.4

* 1.2.0 (2019-03-11)

  * Added new documentation links
  * Bumped minimum version of PHP to 7.1
  * Added support for streamed responses

* 1.1.2 (2019-04-03)

  * Fixed createResponse

* 1.1.1 (2019-03-11)

  * Removed triggering of deprecation

* 1.1.0 (2018-08-30)

  * Deprecated DiactorosFactory, use PsrHttpFactory instead
  * Added option to stream the response on the HttpFoundationFactory createResponse
  * Added more tests and improved code style

* 1.0.2 (2017-12-19)

  * Fixed request target in PSR7 Request (mtibben)

* 1.0.1 (2017-12-04)

  * Added support for Symfony 4 (dunglas)

* 1.0.0 (2016-09-14)

  * Initial release
