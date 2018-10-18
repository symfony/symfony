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
 * Deprecated `SimpleCacheAdapter`, use `Psr16Adapter instead.

Config
------

 * Deprecated using environment variables with `cannotBeEmpty()` if the value is validated with `validate()`

EventDispatcher
---------------

 * The signature of the `EventDispatcherInterface::dispatch()` method should be updated to `dispatch($event, string $eventName = null)`, not doing so is deprecated

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

Messenger
---------

 * `Amqp` transport does not throw `\AMQPException` anymore, catch `TransportException` instead.

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
 * The `RoleHierarchyInterface` is deprecated and will be removed in 5.0.
 * The `getReachableRoles()` method of the `RoleHierarchy` class is deprecated and will be removed in 5.0.
   Use the `getReachableRoleNames()` method instead.
 * The `getRoles()` method of the `TokenInterface` is deprecated. Tokens must implement the `getRoleNames()`
   method instead and return roles as strings.
 * The `ListenerInterface` is deprecated, turn your listeners into callables instead.
 * The `Firewall::handleRequest()` method is deprecated, use `Firewall::callListeners()` instead.
 * The `AbstractToken::serialize()`, `AbstractToken::unserialize()`,
   `AuthenticationException::serialize()` and `AuthenticationException::unserialize()`
   methods are now final, use `getState()` and `setState()` instead.

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
   protected function getState(): array
   {
       return [$this->myLocalVar, parent::getState()];
   }

   protected function setState(array $data)
   {
       [$this->myLocalVar, $parentData] = $data;
       parent::setState($parentData);
   }
   ```

Yaml
----

 * Using a mapping inside a multi-line string is deprecated and will throw a `ParseException` in 5.0.
