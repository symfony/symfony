CHANGELOG
=========

5.3
---

 * The bridge is not marked as `@experimental` anymore
 * [BC BREAK] Change signature of `MattermostTransport::__construct()` method from:
   `public function __construct(string $token, string $channel, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, string $path = null)`
   to:
   `public function __construct(string $token, string $channel, ?string $path = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)`

5.1.0
-----

 * Added the bridge
