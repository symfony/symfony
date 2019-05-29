UPGRADE FROM 4.3 to 4.4
=======================

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

TwigBridge
----------

 * Deprecated to pass `$rootDir` and `$fileLinkFormatter` as 5th and 6th argument respectively to the 
   `DebugCommand::__construct()` method, swap the variables position.
