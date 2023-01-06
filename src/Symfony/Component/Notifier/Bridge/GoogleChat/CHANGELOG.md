CHANGELOG
=========

6.3
---

 * Deprecate `GoogleChatOptions::card()` in favor of `cardV2()`

5.3
---

 * The bridge is not marked as `@experimental` anymore
 * [BC BREAK] Remove `GoogleChatTransport::setThreadKey()` method, this parameter should now be provided via the constructor,
   which has changed from:
   `__construct(string $space, string $accessKey, string $accessToken, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)`
   to:
   `__construct(string $space, string $accessKey, string $accessToken, string $threadKey = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)`
 * [BC BREAK] Rename the parameter `threadKey` to `thread_key` in DSN

5.2.0
-----

 * Added the bridge
