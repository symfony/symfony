UPGRADE FROM 4.2 to 4.3
=======================

BrowserKit
----------

 * Renamed `Client` to `AbstractBrowser`
 * Marked `Response` final.
 * Deprecated `Response::buildHeader()`
 * Deprecated `Response::getStatus()`, use `Response::getStatusCode()` instead

Cache
-----

 * The `psr/simple-cache` dependency has been removed - run `composer require psr/simple-cache` if you need it.
 * Deprecated all PSR-16 adapters, use `Psr16Cache` or `Symfony\Contracts\Cache\CacheInterface` implementations instead.
 * Deprecated `SimpleCacheAdapter`, use `Psr16Adapter` instead.

Config
------

 * Deprecated using environment variables with `cannotBeEmpty()` if the value is validated with `validate()`

DependencyInjection
-------------------

 * Deprecated support for non-string default env() parameters

   Before:
   ```yaml
   parameters:
       env(NAME): 1.5
   ```

   After:
   ```yaml
   parameters:
          env(NAME): '1.5'
   ```

EventDispatcher
---------------

 * The signature of the `EventDispatcherInterface::dispatch()` method should be updated to `dispatch($event, string $eventName = null)`, not doing so is deprecated
 * The `Event` class has been deprecated, use `Symfony\Contracts\EventDispatcher\Event` instead

Form
----

 * Using the `format` option of `DateType` and `DateTimeType` when the `html5` option is enabled is deprecated.
 * Using names for buttons that do not start with a letter, a digit, or an underscore is deprecated and will lead to an
   exception in 5.0.
 * Using names for buttons that do not contain only letters, digits, underscores, hyphens, and colons is deprecated and
   will lead to an exception in 5.0.
 * Using the `date_format`, `date_widget`, and `time_widget` options of the `DateTimeType` when the `widget` option is
   set to `single_text` is deprecated.

FrameworkBundle
---------------

 * Not passing the project directory to the constructor of the `AssetsInstallCommand` is deprecated. This argument will
   be mandatory in 5.0.
 * Deprecated the "Psr\SimpleCache\CacheInterface" / "cache.app.simple" service, use "Symfony\Contracts\Cache\CacheInterface" / "cache.app" instead.
 * The `generate()` method of the `UrlGenerator` class can return an empty string instead of null.

HttpFoundation
--------------

 * The `MimeTypeGuesserInterface` and `ExtensionGuesserInterface` interfaces have been deprecated,
   use `Symfony\Component\Mime\MimeTypesInterface` instead.
 * The `MimeType` and `MimeTypeExtensionGuesser` classes have been deprecated,
   use `Symfony\Component\Mime\MimeTypes` instead.
 * The `FileBinaryMimeTypeGuesser` class has been deprecated,
   use `Symfony\Component\Mime\FileBinaryMimeTypeGuesser` instead.
 * The `FileinfoMimeTypeGuesser` class has been deprecated,
   use `Symfony\Component\Mime\FileinfoMimeTypeGuesser` instead.

HttpKernel
----------

 * Renamed `Client` to `HttpKernelBrowser`
 * Renamed `FilterControllerArgumentsEvent` to `ControllerArgumentsEvent`
 * Renamed `FilterControllerEvent` to `ControllerEvent`
 * Renamed `FilterResponseEvent` to `ResponseEvent`
 * Renamed `GetResponseEvent` to `RequestEvent`
 * Renamed `GetResponseForControllerResultEvent` to `ViewEvent`
 * Renamed `GetResponseForExceptionEvent` to `ExceptionEvent`
 * Renamed `PostResponseEvent` to `TerminateEvent`
 * Deprecated `TranslatorListener` in favor of `LocaleAwareListener`

Messenger
---------

 * `Amqp` transport does not throw `\AMQPException` anymore, catch `TransportException` instead.
 * Deprecated the `LoggingMiddleware` class, pass a logger to `SendMessageMiddleware` instead.

Routing
-------

 * The `generator_base_class`, `generator_cache_class`, `matcher_base_class`, and `matcher_cache_class` router
   options have been deprecated.
 * Implementing `Serializable` for `Route` and `CompiledRoute` is deprecated; if you serialize them, please
   ensure your unserialization logic can recover from a failure related to an updated serialization format

Security
--------

 * The `Role` and `SwitchUserRole` classes are deprecated and will be removed in 5.0. Use strings for roles
   instead.
 * The `getReachableRoles()` method of the `RoleHierarchyInterface` is deprecated and will be removed in 5.0.
   Role hierarchies must implement the `getReachableRoleNames()` method instead and return roles as strings.
 * The `getRoles()` method of the `TokenInterface` is deprecated. Tokens must implement the `getRoleNames()`
   method instead and return roles as strings.
 * The `ListenerInterface` is deprecated, turn your listeners into callables instead.
 * The `Firewall::handleRequest()` method is deprecated, use `Firewall::callListeners()` instead.
 * The `AbstractToken::serialize()`, `AbstractToken::unserialize()`,
   `AuthenticationException::serialize()` and `AuthenticationException::unserialize()`
   methods are now final, use `__serialize()` and `__unserialize()` instead.

   Before:
   ```php
   public function serialize()
   {
       return [$this->myLocalVar, parent::serialize()];
   }

   public function unserialize($serialized)
   {
       [$this->myLocalVar, $parentSerialized] = unserialize($serialized);
       parent::unserialize($parentSerialized);
   }
   ```

   After:
   ```php
   public function __serialize(): array
   {
       return [$this->myLocalVar, parent::__serialize()];
   }

   public function __unserialize(array $data): void
   {
       [$this->myLocalVar, $parentData] = $data;
       parent::__unserialize($parentData);
   }
   ```

Workflow
--------

 * `initial_place` is deprecated in favour of `initial_places`.

   Before:
   ```yaml
   framework:
      workflows:
          article:
              initial_place: draft
   ```

   After:
   ```yaml
   framework:
      workflows:
          article:
              initial_places: [draft]
   ```

Workflow
--------

 * `MarkingStoreInterface::setMarking()` will have a third argument in Symfony 5.0.

   Before:
   ```php
   class MyMarkingStore implements MarkingStoreInterface
   {
       public function setMarking($subject, Marking $marking)
       {
       }
   }
   ```

   After:
   ```php
   class MyMarkingStore implements MarkingStoreInterface
   {
       public function setMarking($subject, Marking $marking , array $context = [])
       {
       }
   }
   ```

 * `MultipleStateMarkingStore` is deprecated. Use `MethodMarkingStore` instead.

   Before:
   ```yaml
   framework:
       workflows:
           type: workflow
           article:
               marking_store:
                   type: multiple
                   arguments: states
   ```

   After:
   ```yaml
   framework:
       workflows:
           type: workflow
           article:
               marking_store:
                   type: method
                   property: states
   ```

 * `SingleStateMarkingStore` is deprecated. Use `MethodMarkingStore` instead.

   Before:
   ```yaml
   framework:
       workflows:
           article:
               marking_store:
                   arguments: state
   ```

   After:
   ```yaml
   framework:
       workflows:
           type: state_machine
           article:
               marking_store:
                   type: method
                   property: state
   ```

 * Using a workflow with a single state marking is deprecated. Use a state machine instead.

   Before:
   ```yaml
   framework:
       workflows:
           article:
               type: workflow
               marking_store:
                   type: single_state
   ```

   After:
   ```yaml
   framework:
       workflows:
           article:
               type: state_machine
               marking_store:
                   # type: single_state # Since the single_state marking store is deprecated, use method instead
                   type: method
   ```

Yaml
----

 * Using a mapping inside a multi-line string is deprecated and will throw a `ParseException` in 5.0.
