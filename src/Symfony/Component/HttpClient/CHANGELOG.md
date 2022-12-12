CHANGELOG
=========

6.2
---

 * Make `HttplugClient` implement `Psr\Http\Message\RequestFactoryInterface`, `StreamFactoryInterface` and `UriFactoryInterface`
 * Deprecate implementing `Http\Message\RequestFactory`, `StreamFactory` and `UriFactory` on `HttplugClient`
 * Add `withOptions()` to `HttplugClient` and `Psr18Client`
 * Add support for "friendsofphp/well-known-implementations"

6.1
---

 * Allow yielding `Exception` from MockResponse's `$body` to mock transport errors
 * Remove credentials from requests redirected to same host but different port

5.4
---

 * Add `MockHttpClient::setResponseFactory()` method to be able to set response factory after client creating

5.3
---

 * Implement `HttpClientInterface::withOptions()` from `symfony/contracts` v2.4
 * Add `DecoratorTrait` to ease writing simple decorators

5.2.0
-----

 * added `AsyncDecoratorTrait` to ease processing responses without breaking async
 * added support for pausing responses with a new `pause_handler` callable exposed as an info item
 * added `StreamableInterface` to ease turning responses into PHP streams
 * added `MockResponse::getRequestMethod()` and `getRequestUrl()` to allow inspecting which request has been sent
 * added `EventSourceHttpClient` a Server-Sent events stream implementing the [EventSource specification](https://www.w3.org/TR/eventsource/#eventsource)
 * added option "extra.curl" to allow setting additional curl options in `CurlHttpClient`
 * added `RetryableHttpClient` to automatically retry failed HTTP requests.
 * added `extra.trace_content` option to `TraceableHttpClient` to prevent it from keeping the content in memory

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
