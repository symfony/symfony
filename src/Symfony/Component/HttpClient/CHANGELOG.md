CHANGELOG
=========

4.4.0
-----

 * added `StreamWrapper`
 * added `HttplugClient`
 * added `max_duration` option
 * added support for NTLM authentication
 * added `$response->toStream()` to cast responses to regular PHP streams
 * made `Psr18Client` implement relevant PSR-17 factories and have streaming responses
 * added `TraceableHttpClient`, `HttpClientDataCollector` and `HttpClientPass` to integrate with the web profiler
 * allow enabling buffering conditionally with a Closure

4.3.0
-----

 * added the component
