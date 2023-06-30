UPGRADE FROM 6.4 to 7.0
=======================

Symfony 6.4 and Symfony 7.0 will be released simultaneously at the end of November 2023. According to the Symfony
release process, both versions will have the same features, but Symfony 7.0 won't include any deprecated features.
To upgrade, make sure to resolve all deprecation notices.

Cache
-----

 * Add parameter `$isSameDatabase` to `DoctrineDbalAdapter::configureSchema()`

Console
-------

 * Remove `Command::$defaultName` and `Command::$defaultDescription`, use the `AsCommand` attribute instead
 * Passing null to `*Command::setApplication()`, `*FormatterStyle::setForeground/setBackground()`, `Helper::setHelpSet()`, `Input*::setDefault()` and `Question::setAutocompleterCallback/setValidator()` must be done explicitly
 * Remove `StringInput::REGEX_STRING`

DependencyInjection
-------------------

 * Remove `#[MapDecorated]`, use `#[AutowireDecorated]` instead
 * Remove `ProxyHelper`, use `Symfony\Component\VarExporter\ProxyHelper` instead
 * Remove `ReferenceSetArgumentTrait`
 * Remove support of `@required` annotation, use the `Symfony\Contracts\Service\Attribute\Required` attribute instead
 * Passing `null` to `ContainerAwareTrait::setContainer()` must be done explicitly
 * Remove `PhpDumper` options `inline_factories_parameter` and `inline_class_loader_parameter`, use options `inline_factories` and `inline_class_loader` instead
 * Parameter names of `ParameterBag` cannot be numerics
 * Remove `ContainerAwareInterface` and `ContainerAwareTrait`, use dependency injection instead

DoctrineBridge
--------------

 * Remove `DoctrineDbalCacheAdapterSchemaSubscriber`, use `DoctrineDbalCacheAdapterSchemaListener` instead
 * Remove `MessengerTransportDoctrineSchemaSubscriber`, use `MessengerTransportDoctrineSchemaListener` instead
 * Remove `RememberMeTokenProviderDoctrineSchemaSubscriber`, use `RememberMeTokenProviderDoctrineSchemaListener` instead
 * Remove `DbalLogger`, use a middleware instead
 * Remove `DoctrineDataCollector::addLogger()`, use a `DebugDataHolder` instead
 * Remove `ContainerAwareLoader`, use dependency injection in your fixtures instead
 * `ContainerAwareEventManager::getListeners()` must be called with an event name
 * DoctrineBridge now requires `doctrine/event-manager:^2`
 * Add parameter `$isSameDatabase` to `DoctrineTokenProvider::configureSchema()`

HttpFoundation
--------------

 * Calling `ParameterBag::filter()` on an invalid value throws an `UnexpectedValueException` instead of returning `false`.
   The exception is more specific for `InputBag` which throws a `BadRequestException` when invalid value is found.
   The flag `FILTER_NULL_ON_FAILURE` can be used to return `null` instead of throwing an exception.
 * The methods `ParameterBag::getInt()` and `ParameterBag::getBool()` no longer fallback to `0` or `false`
   when the value cannot be converted to the expected type. They throw a `UnexpectedValueException` instead.
 * Replace `RequestMatcher` with `ChainRequestMatcher`
 * Replace `ExpressionRequestMatcher` with `RequestMatcher\ExpressionRequestMatcher`
 * Remove `Request::getContentType()`, use `Request::getContentTypeFormat()` instead
 * Throw an `InvalidArgumentException` when calling `Request::create()` with a malformed URI

Lock
----

 * Add parameter `$isSameDatabase` to `DoctrineDbalStore::configureSchema()`

Messenger
---------

 * Add parameter `$isSameDatabase` to `DoctrineTransport::configureSchema()`

ProxyManagerBridge
------------------

 * Remove the bridge, use VarExporter's lazy objects instead

SecurityBundle
--------------

 * Enabling SecurityBundle and not configuring it is not allowed

Serializer
----------

 * Remove denormalization support for `AbstractUid` in `UidNormalizer`, use one of `AbstractUid` child class instead
 * Denormalizing to an abstract class in `UidNormalizer` now throws an `\Error`
 * Remove `ContextAwareDenormalizerInterface`, use `DenormalizerInterface` instead
 * Remove `ContextAwareNormalizerInterface`, use `NormalizerInterface` instead
 * Remove `CacheableSupportsMethodInterface`, use `NormalizerInterface` and `DenormalizerInterface` instead
 * First argument of `ClassMetadata::setSerializedName()` is now required
 * Third argument `array $context = []` of the `NormalizerInterface::supportsNormalization()` is now required
 * Fourth argument `array $context = []` of the `DenormalizerInterface::supportsDenormalization()` is now required
