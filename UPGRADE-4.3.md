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
 * Deprecated the `root()` method in `TreeBuilder`, pass the root node information to the constructor instead

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

Doctrine Bridge
---------------

 * Passing an `IdReader` to the `DoctrineChoiceLoader` when the query cannot be optimized with single id field has been deprecated, pass `null` instead
 * Not passing an `IdReader` to the `DoctrineChoiceLoader` when the query can be optimized with single id field has been deprecated

Dotenv
------

 * First parameter of `Dotenv::__construct()` will be changed from `true` to `false` in Symfony 5.0. A deprecation warning
   is triggered if no parameter is provided. Use `$usePutenv = true` to upgrade without breaking changes.

EventDispatcher
---------------

 * The signature of the `EventDispatcherInterface::dispatch()` method has been updated, consider using the new signature `dispatch($event, string $eventName = null)` instead of the old signature `dispatch($eventName, $event)` that is deprecated

   You have to swap arguments when calling `dispatch()`:

   Before:
   ```php
   $this->eventDispatcher->dispatch(Events::My_EVENT, $event);
   ```

   After:
   ```php
   $this->eventDispatcher->dispatch($event, Events::My_EVENT);
   ```

   If your bundle or package needs to provide compatibility with the previous way of using the dispatcher, you can use `Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy::decorate()` to ease upgrades:

   Before:
   ```php
   public function __construct(EventDispatcherInterface $eventDispatcher) {
       $this->eventDispatcher = $eventDispatcher;
   }
   ```

   After:
   ```php
   public function __construct(EventDispatcherInterface $eventDispatcher) {
       $this->eventDispatcher = LegacyEventDispatcherProxy::decorate($eventDispatcher);
   }
   ```

 * The `Event` class has been deprecated, use `Symfony\Contracts\EventDispatcher\Event` instead

Filesystem
----------

 * Support for passing arrays to `Filesystem::dumpFile()` is deprecated.
 * Support for passing arrays to `Filesystem::appendToFile()` is deprecated.

Form
----

 * Using the `format` option of `DateType` and `DateTimeType` when the `html5` option is enabled is deprecated.
 * Using names for buttons that do not start with a lowercase letter, a digit, or an underscore is deprecated and will lead to an
   exception in 5.0.
 * Using names for buttons that do not contain only letters, digits, underscores, hyphens, and colons is deprecated and
   will lead to an exception in 5.0.
 * Using the `date_format`, `date_widget`, and `time_widget` options of the `DateTimeType` when the `widget` option is
   set to `single_text` is deprecated.

FrameworkBundle
---------------

 * Deprecated the `framework.templating` option, configure the Twig bundle instead.
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

Intl
----

 * Deprecated `ResourceBundle` namespace
 * Deprecated `Intl::getCurrencyBundle()`, use `Currencies` instead
 * Deprecated `Intl::getLanguageBundle()`, use `Languages` or `Scripts` instead
 * Deprecated `Intl::getLocaleBundle()`, use `Locales` instead
 * Deprecated `Intl::getRegionBundle()`, use `Countries` instead

Messenger
---------

 * `Amqp` transport does not throw `\AMQPException` anymore, catch `TransportException` instead.
 * Deprecated the `LoggingMiddleware` class, pass a logger to `SendMessageMiddleware` instead.

Routing
-------

 * The `generator_base_class`, `generator_cache_class`, `matcher_base_class`, and `matcher_cache_class` router
   options have been deprecated.
 * `Serializable` implementing methods for `Route` and `CompiledRoute` are marked as `@internal` and `@final`.
   Instead of overwriting them, use `__serialize` and `__unserialize` as extension points which are forward compatible
   with the new serialization methods in PHP 7.4.

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

 * The `Argon2iPasswordEncoder` class has been deprecated, use `SodiumPasswordEncoder` instead.
 * The `BCryptPasswordEncoder` class has been deprecated, use `NativePasswordEncoder` instead.
 * Not implementing the methods `__serialize` and `__unserialize` in classes implementing
   the `TokenInterface` is deprecated

TwigBridge
----------

 * deprecated the `$requestStack` and `$requestContext` arguments of the
   `HttpFoundationExtension`, pass a `Symfony\Component\HttpFoundation\UrlHelper`
   instance as the only argument instead

Workflow
--------

 * `initial_place` is deprecated in favour of `initial_marking`.

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
              initial_marking: [draft]
   ```

 * `WorkflowInterface::apply()` will have a third argument in Symfony 5.0.

   Before:
   ```php
   class MyWorkflow implements WorkflowInterface
   {
       public function apply($subject, $transitionName)
       {
       }
   }
   ```

   After:
   ```php
   class MyWorkflow implements WorkflowInterface
   {
       public function apply($subject, $transitionName, array $context = [])
       {
       }
   }
   ```

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
           article:
               type: workflow
               marking_store:
                   type: multiple_state
                   arguments: states
   ```

   After:
   ```yaml
   framework:
       workflows:
           article:
               type: workflow
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
           article:
               type: state_machine
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

 * Using `DefinitionBuilder::setInitialPlace()` is deprecated, use `DefinitionBuilder::setInitialPlaces()` instead.

Yaml
----

 * Using a mapping inside a multi-line string is deprecated and will throw a `ParseException` in 5.0.
