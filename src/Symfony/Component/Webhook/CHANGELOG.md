CHANGELOG
=========

7.1
---

 * Added `Symfony\Component\Webhook\Event\WebhookSentEvent`
 * Added an optional dependency to `Psr\EventDispatcher\EventDispatcherInterface` in `Symfony\Component\Webhook\Server\Transport`
 * Dispatch `WebhookSentEvent` after a webhook is called

6.4
---

 * Mark the component as non experimental

6.3
---

 * Add the component (experimental)
