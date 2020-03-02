CHANGELOG
=========

5.1.0
-----

 * added `NoPrivateNetworkHttpClient` decorator
 * added `AmpHttpClient`, a portable HTTP/2 implementation based on Amp
 * added `LoggerAwareInterface` to `ScopingHttpClient` and `TraceableHttpClient`
 * made `HttpClient::create()` return an `AmpHttpClient` when `amphp/http-client` is found but curl is not or too old

4.4.0
-----

 * added `canceled` to `ResponseInterface::getInfo()`
 * added `HttpClient::createForBaseUri()`
 * added `HttplugClient` with support for sync and async requests
 * added `max_duration` option
 * added support for NTLM authentication
 * added `StreamWrapper` to cast any `ResponseInterface` instances to PHP streams.
 * added `$response->toStream()` to cast responses to regular PHP streams
 * made `Psr18Client` implement relevant PSR-17 factories and have streaming responses
 * added `TraceableHttpClient`, `HttpClientDataCollector` and `HttpClientPass` to integrate with the web profiler
 * allow enabling buffering conditionally with a Closure
 * allow option "buffer" to be a stream resource
 * allow arbitrary values for the "json" option

4.3.0
-----

 * added the component
