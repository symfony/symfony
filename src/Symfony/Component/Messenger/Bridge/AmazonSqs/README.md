Amazon SQS Messenger
====================

Provides Amazon SQS integration for Symfony Messenger.

Available stamps
----------------

- `Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFifoStamp`
  - Use with [FIFO queues](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-fifo-queues.html) (queue name ends with `.fifo`).
  - Set per-message "Message group ID" and optional "Message deduplication ID".
  - If both FIFO and FairQueue stamps are present, FIFO takes precedence.

- `Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFairQueueStamp`
  - Enables [fair queue](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-fair-queues.html) processing on standard queues by setting `MessageGroupId`.
  - Use a tenant/group identifier to balance processing across groups.
  - Has no effect on FIFO queues. Ignored when FIFO stamp is present.

- `Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsXrayTraceHeaderStamp`
  - Adds an AWS X-Ray `TraceId` to the outgoing SQS message attributes.

- `Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsReceivedStamp`
  - Carries the SQS `ReceiptHandle`/message identifier for received messages.

Notes
-----

- Middleware: `Symfony\Component\Messenger\Bridge\AmazonSqs\Middleware\AddFifoStampMiddleware`
  - If the message implements `MessageDeduplicationAwareInterface`, the middleware adds `AmazonSqsFifoStamp` and sets the deduplication ID.
  - If the message implements `MessageGroupAwareInterface`, the middleware sets the group ID on the stamp.

- FIFO queues do not support per-message delay. Configure retry strategy with `delay: 0`.

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
