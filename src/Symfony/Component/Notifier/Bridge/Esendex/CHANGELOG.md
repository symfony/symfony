CHANGELOG
=========

6.2
---

 * Use `SmsMessage->from` when defined

5.4
---

 * Add returned message ID to `SentMessage`

5.3
---

 * The bridge is not marked as `@experimental` anymore
 * [BC BREAK] Change signature of `EsendexTransport::__construct()` method from:
   `public function __construct(string $token, string $accountReference, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)`
   to:
   `public function __construct(string $email, string $password, string $accountReference, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)`

5.2.0
-----

 * Added the bridge
