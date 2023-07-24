UPGRADE FROM 6.3 to 6.4
=======================

Cache
-----

 * `EarlyExpirationHandler` no longer implements `MessageHandlerInterface`, rely on `AsMessageHandler` instead

DependencyInjection
-------------------

 * Deprecate `ContainerAwareInterface` and `ContainerAwareTrait`, use dependency injection instead

DoctrineBridge
--------------

 * Deprecate `DbalLogger`, use a middleware instead
 * Deprecate not constructing `DoctrineDataCollector` with an instance of `DebugDataHolder`
 * Deprecate `DoctrineDataCollector::addLogger()`, use a `DebugDataHolder` instead
 * Deprecate `ContainerAwareLoader`, use dependency injection in your fixtures instead

ErrorHandler
------------

 * `FlattenExceptionNormalizer` no longer implements `ContextAwareNormalizerInterface`

Form
----

 * Deprecate using `DateTime` or `DateTimeImmutable` model data with a different timezone than configured with the
   `model_timezone` option in `DateType`, `DateTimeType`, and `TimeType`
 * Deprecate `PostSetDataEvent::setData()`, use `PreSetDataEvent::setData()` instead
 * Deprecate `PostSubmitEvent::setData()`, use `PreSubmitDataEvent::setData()` or `SubmitDataEvent::setData()` instead

FrameworkBundle
---------------

 * Add native return type to `Translator` and to `Application::reset()`
 * Deprecate the integration of Doctrine annotations, either uninstall the `doctrine/annotations` package or disable
   the integration by setting `framework.annotations` to `false`

HttpFoundation
--------------

 * Make `HeaderBag::getDate()`, `Response::getDate()`, `getExpires()` and `getLastModified()` return a `DateTimeImmutable`

HttpKernel
----------

 * `BundleInterface` no longer extends `ContainerAwareInterface`
 * Add native return types to `TraceableEventDispatcher` and to `MergeExtensionConfigurationPass`

Messenger
---------

 * Deprecate `StopWorkerOnSignalsListener` in favor of using the `SignalableCommandInterface`

MonologBridge
-------------

 * Add native return type to `Logger::clear()` and to `DebugProcessor::clear()`

Routing
-------

 * Add native return type to `AnnotationClassLoader::setResolver()`
 * Deprecate Doctrine annotations support in favor of native attributes
 * Change the constructor signature of `AnnotationClassLoader` to `__construct(?string $env = null)`, passing an annotation reader as first argument is deprecated

Security
--------

 * `UserValueResolver` no longer implements `ArgumentValueResolverInterface`
 * Make `PersistentToken` immutable
 * Deprecate accepting only `DateTime` for `TokenProviderInterface::updateToken()`, use `DateTimeInterface` instead

Serializer
----------

 * Deprecate Doctrine annotations support in favor of native attributes
 * Deprecate passing an annotation reader to the constructor of `AnnotationLoader`

Validator
---------

 * Deprecate Doctrine annotations support in favor of native attributes
 * Deprecate passing an annotation reader to the constructor signature of `AnnotationLoader`
 * Deprecate `ValidatorBuilder::setDoctrineAnnotationReader()`
 * Deprecate `ValidatorBuilder::addDefaultDoctrineAnnotationReader()`
