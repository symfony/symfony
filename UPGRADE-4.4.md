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

HttpKernel
----------

* The method `FilterControllerArgumentsEvent::getArguments()` marked final.
* The method `FilterControllerEvent::getController()` marked final.
* The method `FilterResponseEvent::getResponse()` marked final.
* The method `GetResponseForExceptionEvent::getException()` marked final.
* The method `PostResponseEvent::getResponse()` marked final.