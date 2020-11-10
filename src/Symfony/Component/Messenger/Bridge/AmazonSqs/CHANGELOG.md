CHANGELOG
=========

5.3.0
-----

 * Made it possible to re-use the Connection configuration processing logic
   outside the Connection class, e.g. to enable instantiating an SqsClient
   on your own.

5.2.0
-----

 * Added support for an Amazon SQS QueueUrl to be used as DSN.

5.1.0
-----

 * Introduced the Amazon SQS bridge.
 * Added FIFO support to the SQS transport
