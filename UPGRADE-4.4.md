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
 * Deprecated `tagged` in favor of `tagged_iterator`

   Before:
   ```yaml
   services:
       App\Handler:
           tags: ['app.handler']

       App\HandlerCollection:
           arguments: [!tagged app.handler]
   ```

   After:
   ```yaml
   services:
       App\Handler:
       tags: ['app.handler']

   App\HandlerCollection:
       arguments: [!tagged_iterator app.handler]
   ```

 * Passing an instance of `Symfony\Component\DependencyInjection\Parameter` as class name to `Symfony\Component\DependencyInjection\Definition` is deprecated.

   Before:
   ```php
   new Definition(new Parameter('my_class'));
   ```

   After:
   ```php
   new Definition('%my_class%');
   ```

Filesystem
----------

 * Support for passing a `null` value to `Filesystem::isAbsolutePath()` is deprecated.

Form
----

 * Using `int` or `float` as data for the `NumberType` when the `input` option is set to `string` is deprecated.

FrameworkBundle
---------------

 * Deprecated support for `templating` engine in `TemplateController`, use Twig instead
 * The `$parser` argument of `ControllerResolver::__construct()` and `DelegatingLoader::__construct()`
   has been deprecated.
 * The `ControllerResolver` and `DelegatingLoader` classes have been marked as `final`.
 * The `controller_name_converter` and `resolve_controller_name_subscriber` services have been deprecated.

HttpClient
----------

 * Added method `cancel()` to `ResponseInterface`

HttpFoundation
--------------

 * `ApacheRequest` is deprecated, use `Request` class instead.

HttpKernel
----------

 * The `DebugHandlersListener` class has been marked as `final`

Messenger
---------

 * Deprecated passing a `ContainerInterface` instance as first argument of the `ConsumeMessagesCommand` constructor,
   pass a `RoutableMessageBus`  instance instead.

MonologBridge
--------------

 * The `RouteProcessor` has been marked final.

PropertyAccess
--------------

 * Deprecated passing `null` as 2nd argument of `PropertyAccessor::createCache()` method (`$defaultLifetime`), pass `0` instead.

Security
--------

 * Implementations of `PasswordEncoderInterface` and `UserPasswordEncoderInterface` should add a new `needsRehash()` method

Stopwatch
---------

 * Deprecated passing `null` as 1st (`$id`) argument of `Section::get()` method, pass a valid child section identifier instead.

TwigBridge
----------

 * Deprecated to pass `$rootDir` and `$fileLinkFormatter` as 5th and 6th argument respectively to the
   `DebugCommand::__construct()` method, swap the variables position.

Validator
---------

 * Deprecated passing an `ExpressionLanguage` instance as the second argument of `ExpressionValidator::__construct()`.
 * Deprecated using anything else than a `string` as the code of a `ConstraintViolation`, a `string` type-hint will
   be added to the constructor of the `ConstraintViolation` class and to the `ConstraintViolationBuilder::setCode()`
   method in 5.0.
 * Deprecated passing an `ExpressionLanguage` instance as the second argument of `ExpressionValidator::__construct()`. 
   Pass it as the first argument instead.
 * The `Length` constraint expects the `allowEmptyString` option to be defined
   when the `min` option is used.
   Set it to `true` to keep the current behavior and `false` to reject empty strings.
   In 5.0, it'll become optional and will default to `false`.
