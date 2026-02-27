CHANGELOG
=========

8.2
---

 * Stop deduplicating the messages sent to a FIFO queue on their content: the default `MessageDeduplicationId` is now unique per send
 * Allow auto-setup to create the queue when the DSN names the account the client already authenticates as
 * Add `AmazonSqsFairQueueStamp` to set a `MessageGroupId` on standard queues, enabling SQS fair queues
 * Add the `ssl` DSN option, superseding `sslmode`
 * Add `AmazonSqsReceivedStamp::getSystemAttributes()` exposing the SQS system attributes of received messages

7.4
---

* Allow SQS to handle its own retry/DLQ
* Add `retry_delay` option to configure the delay between retries when using SQS retry/DLQ handling

7.3
---

 * Implement the `CloseableTransportInterface` to allow closing the transport
 * Add new `queue_attributes` and `queue_tags` options for SQS queue creation

7.2
---

 * Implement the `KeepaliveReceiverInterface` to enable asynchronously notifying SQS that the job is still being processed, in order to avoid timeouts

6.4
---

 * Add `AddFifoStampMiddleware` to help adding `AmazonSqsFifoStamp`

6.1
---

 * Added `session_token` option to support short-lived AWS credentials

5.3
---

 * Added new `debug` option to log HTTP requests and responses.
 * Allowed for receiver & sender injection into AmazonSqsTransport
 * Add X-Ray trace header support to the SQS transport

5.2.0
-----

 * Added support for an Amazon SQS QueueUrl to be used as DSN.

5.1.0
-----

 * Introduced the Amazon SQS bridge.
 * Added FIFO support to the SQS transport
