CHANGELOG
=========

7.3
---

 * Add `BeanstalkdPriorityStamp` option to allow setting the message priority
 * Add `bury_on_reject` option to bury failed messages instead of deleting them

7.2
---

 * Implement the `KeepaliveReceiverInterface` to enable asynchronously notifying Beanstalkd that the job is still being processed, in order to avoid timeouts

5.2.0
-----

 * Introduced the Beanstalkd bridge.
