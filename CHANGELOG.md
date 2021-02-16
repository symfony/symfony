CHANGELOG
=========

# 2.1.0 (2021-02-17)

  * Added a `PsrResponseListener` to automatically convert PSR-7 responses returned by controllers
  * Added a `PsrServerRequestResolver` that allows injecting PSR-7 request objects into controllers

# 2.0.2 (2020-09-29)

  * Fix populating server params from URI in HttpFoundationFactory
  * Create cookies as raw in HttpFoundationFactory
  * Fix BinaryFileResponse with Content-Range PsrHttpFactory

# 2.0.1 (2020-06-25)

  * Don't normalize query string in PsrHttpFactory
  * Fix conversion for HTTPS requests
  * Fix populating default port and headers in HttpFoundationFactory

# 2.0.0 (2020-01-02)

  * Remove DiactorosFactory

# 1.3.0 (2019-11-25)

  * Added support for streamed requests
  * Added support for Symfony 5.0+
  * Fixed bridging UploadedFile objects
  * Bumped minimum version of Symfony to 4.4

# 1.2.0 (2019-03-11)

  * Added new documentation links
  * Bumped minimum version of PHP to 7.1
  * Added support for streamed responses

# 1.1.2 (2019-04-03)

  * Fixed createResponse

# 1.1.1 (2019-03-11)

  * Deprecated DiactorosFactory, use PsrHttpFactory instead
  * Removed triggering of deprecation

# 1.1.0 (2018-08-30)

  * Added support for creating PSR-7 messages using PSR-17 factories

# 1.0.2 (2017-12-19)

  * Fixed request target in PSR7 Request (mtibben)

# 1.0.1 (2017-12-04)

  * Added support for Symfony 4 (dunglas)

# 1.0.0 (2016-09-14)

  * Initial release
