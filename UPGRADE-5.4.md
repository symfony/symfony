UPGRADE FROM 5.3 to 5.4
=======================

Messenger
---------
* Deprecate `Middleware\RejectRedeliveredMessageMiddleware`. Install `symfony/amqp-messenger` and use same class from there.


FrameworkBundle
---------------

 * Deprecate the `AdapterInterface` autowiring alias, use `CacheItemPoolInterface` instead
 * Deprecate the public `profiler` service to private

HttpKernel
----------

 * Deprecate `AbstractTestSessionListener::getSession` inject a session in the request instead
