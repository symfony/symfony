UPGRADE FROM 5.x to 6.0
=======================

Asset
-----

 * Removed `RemoteJsonManifestVersionStrategy`, use `JsonManifestVersionStrategy` instead.

Config
------

 * The signature of method `NodeDefinition::setDeprecated()` has been updated to `NodeDefinition::setDeprecation(string $package, string $version, string $message)`.
 * The signature of method `BaseNode::setDeprecated()` has been updated to `BaseNode::setDeprecation(string $package, string $version, string $message)`.
 * Passing a null message to `BaseNode::setDeprecated()` to un-deprecate a node is not supported anymore.
 * Removed `BaseNode::getDeprecationMessage()`, use `BaseNode::getDeprecation()` instead.

Console
-------

 * `Command::setHidden()` has a default value (`true`) for `$hidden` parameter

DependencyInjection
-------------------

 * The signature of method `Definition::setDeprecated()` has been updated to `Definition::setDeprecation(string $package, string $version, string $message)`.
 * The signature of method `Alias::setDeprecated()` has been updated to `Alias::setDeprecation(string $package, string $version, string $message)`.
 * The signature of method `DeprecateTrait::deprecate()` has been updated to `DeprecateTrait::deprecation(string $package, string $version, string $message)`.
 * Removed the `Psr\Container\ContainerInterface` and `Symfony\Component\DependencyInjection\ContainerInterface` aliases of the `service_container` service,
   configure them explicitly instead.
 * Removed `Definition::getDeprecationMessage()`, use `Definition::getDeprecation()` instead.
 * Removed `Alias::getDeprecationMessage()`, use `Alias::getDeprecation()` instead.
 * The `inline()` function from the PHP-DSL has been removed, use `inline_service()` instead.
 * The `ref()` function from the PHP-DSL has been removed, use `service()` instead.
 * Removed `Definition::setPrivate()` and `Alias::setPrivate()`, use `setPublic()` instead

DomCrawler
----------

 * Removed the `parents()` method, use `ancestors()` instead.

Dotenv
------

 * Removed argument `$usePutenv` from Dotenv's constructor, use `Dotenv::usePutenv()` instead.

EventDispatcher
---------------

 * Removed `LegacyEventDispatcherProxy`. Use the event dispatcher without the proxy.

Form
----

 * The default value of the `rounding_mode` option of the `PercentType` has been changed to `\NumberFormatter::ROUND_HALFUP`.
 * The default rounding mode of the `PercentToLocalizedStringTransformer` has been changed to `\NumberFormatter::ROUND_HALFUP`.
 * Added the `getIsEmptyCallback()` method to the `FormConfigInterface`.
 * Added the `setIsEmptyCallback()` method to the `FormConfigBuilderInterface`.
 * Added argument `callable|null $filter` to `ChoiceListFactoryInterface::createListFromChoices()` and `createListFromLoader()`.
 * The `Symfony\Component\Form\Extension\Validator\Util\ServerParams` class has been removed, use its parent `Symfony\Component\Form\Util\ServerParams` instead.
 * The `NumberToLocalizedStringTransformer::ROUND_*` constants have been removed, use `\NumberFormatter::ROUND_*` instead.
 * Removed `PropertyPathMapper` in favor of `DataMapper` and `PropertyPathAccessor`.
 * Changed `$forms` parameter type of the `DataMapper::mapDataToForms()` method from `iterable` to `\Traversable`.
 * Changed `$forms` parameter type of the `DataMapper::mapFormsToData()` method from `iterable` to `\Traversable`.
 * Changed `$checkboxes` parameter type of the `CheckboxListMapper::mapDataToForms()` method from `iterable` to `\Traversable`.
 * Changed `$checkboxes` parameter type of the `CheckboxListMapper::mapFormsToData()` method from `iterable` to `\Traversable`.
 * Changed `$radios` parameter type of the `RadioListMapper::mapDataToForms()` method from `iterable` to `\Traversable`.
 * Changed `$radios` parameter type of the `RadioListMapper::mapFormsToData()` method from `iterable` to `\Traversable`.

FrameworkBundle
---------------

 * `MicroKernelTrait::configureRoutes()` is now always called with a `RoutingConfigurator`
 * The "framework.router.utf8" configuration option defaults to `true`
 * Removed `session.attribute_bag` service and `session.flash_bag` service.
 * The `form.factory`, `form.type.file`, `translator`, `security.csrf.token_manager`, `serializer`,
   `cache_clearer`, `filesystem` and `validator` services are now private.
 * Removed the `lock.RESOURCE_NAME` and `lock.RESOURCE_NAME.store` services and the `lock`, `LockInterface`, `lock.store` and `PersistingStoreInterface` aliases, use `lock.RESOURCE_NAME.factory`, `lock.factory` or `LockFactory` instead.

HttpFoundation
--------------

 * Removed `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, `StreamedResponse::create()` and
   `BinaryFileResponse::create()` methods (use `__construct()` instead)
 * Not passing a `Closure` together with `FILTER_CALLBACK` to `ParameterBag::filter()` throws an `InvalidArgumentException`; wrap your filter in a closure instead.
 * Removed the `Request::HEADER_X_FORWARDED_ALL` constant, use either `Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO` or `Request::HEADER_X_FORWARDED_AWS_ELB` or `Request::HEADER_X_FORWARDED_TRAEFIK`constants instead.

HttpKernel
----------

 * Made `WarmableInterface::warmUp()` return a list of classes or files to preload on PHP 7.4+
 * Removed support for `service:action` syntax to reference controllers. Use `serviceOrFqcn::method` instead.

Inflector
---------

 * The component has been removed, use `EnglishInflector` from the String component instead.

Lock
----

 * Removed the `NotSupportedException`. It shouldn't be thrown anymore.
 * Removed the `RetryTillSaveStore`. Logic has been moved in `Lock` and is not needed anymore.

Mailer
------

 * Removed the `SesApiTransport` class. Use `SesApiAsyncAwsTransport` instead.
 * Removed the `SesHttpTransport` class. Use `SesHttpAsyncAwsTransport` instead.

Messenger
---------

 * Removed AmqpExt transport. Run `composer require symfony/amqp-messenger` to keep the transport in your application.
 * Removed Doctrine transport. Run `composer require symfony/doctrine-messenger` to keep the transport in your application.
 * Removed RedisExt transport. Run `composer require symfony/redis-messenger` to keep the transport in your application.
 * Use of invalid options in Redis and AMQP connections now throws an error.
 * The signature of method `RetryStrategyInterface::isRetryable()` has been updated to `RetryStrategyInterface::isRetryable(Envelope $message, \Throwable $throwable = null)`.
 * The signature of method `RetryStrategyInterface::getWaitingTime()` has been updated to `RetryStrategyInterface::getWaitingTime(Envelope $message, \Throwable $throwable = null)`.
 * Removed the `prefetch_count` parameter in the AMQP bridge.

Mime
----

 * Removed `Address::fromString()`, use `Address::create()` instead

Monolog
-------

 * The `$actionLevel` constructor argument of `Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy` has been replaced by the `$inner` one which expects an ActivationStrategyInterface to decorate instead. `Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy` is now final.
 * The `$actionLevel` constructor argument of `Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy` has been replaced by the `$inner` one which expects an ActivationStrategyInterface to decorate instead. `Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy` is now final.

OptionsResolver
---------------

 * The signature of method `OptionsResolver::setDeprecated()` has been updated to `OptionsResolver::setDeprecated(string $option, string $package, string $version, $message)`.
 * Removed `OptionsResolverIntrospector::getDeprecationMessage()`, use `OptionsResolverIntrospector::getDeprecation()` instead.

PhpUnitBridge
-------------

 * Removed support for `@expectedDeprecation` annotations, use the `ExpectDeprecationTrait::expectDeprecation()` method instead.
 * Removed the `SetUpTearDownTrait` trait, use original methods with "void" return typehint.

PropertyAccess
--------------

 * Dropped support of a boolean as the first argument of `PropertyAccessor::__construct()`.
   Pass a combination of bitwise flags instead.

PropertyInfo
------------

 * Dropped the `enable_magic_call_extraction` context option in `ReflectionExtractor::getWriteInfo()` and `ReflectionExtractor::getReadInfo()` in favor of `enable_magic_methods_extraction`.

Routing
-------

 * Removed `RouteCollectionBuilder`.
 * Added argument `$priority` to `RouteCollection::add()`
 * Removed the `RouteCompiler::REGEX_DELIMITER` constant

Security
--------

 * Removed `ROLE_PREVIOUS_ADMIN` role in favor of `IS_IMPERSONATOR` attribute
 * Removed `LogoutSuccessHandlerInterface` and `LogoutHandlerInterface`, register a listener on the `LogoutEvent` event instead.
 * Removed `DefaultLogoutSuccessHandler` in favor of `DefaultLogoutListener`.
 * Added a `logout(Request $request, Response $response, TokenInterface $token)` method to the `RememberMeServicesInterface`.
 * Removed `setProviderKey()`/`getProviderKey()` in favor of `setFirewallName()/getFirewallName()`
   in `PreAuthenticatedToken`, `RememberMeToken`, `SwitchUserToken`, `UsernamePasswordToken`,
   `DefaultAuthenticationSuccessHandler`.
 * Removed the `AbstractRememberMeServices::$providerKey` property in favor of `AbstractRememberMeServices::$firewallName`
 * `AccessDecisionManager` now throw an exception when a voter does not return a valid decision.

Serializer
----------

 * Removed `ArrayDenormalizer::setSerializer()`, call `setDenormalizer()` instead.
 * `ArrayDenormalizer` does not implement `SerializerAwareInterface` anymore.

TwigBundle
----------

 * The `twig` service is now private.

Validator
---------

 * Removed the `allowEmptyString` option from the `Length` constraint.

   Before:

   ```php
   use Symfony\Component\Validator\Constraints as Assert;

   /**
    * @Assert\Length(min=5, allowEmptyString=true)
    */
   ```

   After:

   ```php
   use Symfony\Component\Validator\Constraints as Assert;

   /**
    * @Assert\AtLeastOneOf({
    *     @Assert\Blank(),
    *     @Assert\Length(min=5)
    * })
    */
   ```

 * Removed the `NumberConstraintTrait` trait.

 * `ValidatorBuilder::enableAnnotationMapping()` does not accept a Doctrine annotation reader anymore.

  Before:

  ```php
  $builder->enableAnnotationMapping($reader);
  ```

  After:

  ```php
  $builder->enableAnnotationMapping(true)
      ->setDoctrineAnnotationReader($reader);
  ```

 * `ValidatorBuilder::enableAnnotationMapping()` won't automatically setup a Doctrine annotation reader anymore.

  Before:

  ```php
  $builder->enableAnnotationMapping();
  ```

  After:

  ```php
  $builder->enableAnnotationMapping(true)
      ->addDefaultDoctrineAnnotationReader();
  ```

Yaml
----

 * Added support for parsing numbers prefixed with `0o` as octal numbers.
 * Removed support for parsing numbers starting with `0` as octal numbers. They will be parsed as strings. Prefix numbers with `0o`
   so that they are parsed as octal numbers.

   Before:

   ```yaml
   Yaml::parse('072');
   ```

   After:

   ```yaml
   Yaml::parse('0o72');
   ```

 * Removed support for using the `!php/object` and `!php/const` tags without a value.
