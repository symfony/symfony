CHANGELOG
=========

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
