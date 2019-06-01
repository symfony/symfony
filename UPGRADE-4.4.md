UPGRADE FROM 4.3 to 4.4
=======================

HttpKernel
----------

 * The `DebugHandlersListener` class has been marked as `final`

DependencyInjection
-------------------

 * Deprecated support for short factories and short configurators in Yaml

   Before:
   ```yaml
   services:
     my_service:
       factory: factory_service:method
   ```

   After:
   ```yaml
   services:
     my_service:
       factory: ['@factory_service', method]
   ```

Messenger
---------

 * Deprecated passing a `ContainerInterface` instance as first argument of the `ConsumeMessagesCommand` constructor,
   pass a `RoutableMessageBus`  instance instead.

MonologBridge
--------------

 * The `RouteProcessor` has been marked final.

TwigBridge
----------

 * Deprecated to pass `$rootDir` and `$fileLinkFormatter` as 5th and 6th argument respectively to the 
   `DebugCommand::__construct()` method, swap the variables position.
