UPGRADE FROM 6.3 to 6.4
=======================

BrowserKit
----------

 * Add argument `$serverParameters` to `AbstractBrowser::click()` and `AbstractBrowser::clickLink()`

Cache
-----

 * [BC break] `EarlyExpirationHandler` no longer implements `MessageHandlerInterface`, rely on `AsMessageHandler` instead

DependencyInjection
-------------------

 * Deprecate `ContainerAwareInterface` and `ContainerAwareTrait`, use dependency injection instead

   *Before*
   ```php
   class MailingListService implements ContainerAwareInterface
   {
       use ContainerAwareTrait;

       public function sendMails()
       {
           $mailer = $this->container->get('mailer');

           // ...
       }
   }
   ```

   *After*
   ```php
   use Symfony\Component\Mailer\MailerInterface;

   class MailingListService
   {
       public function __construct(
           private MailerInterface $mailer,
       ) {
       }

       public function sendMails()
       {
           $mailer = $this->mailer;

           // ...
       }
   }
   ```

   To fetch services lazily, you can use a [service subscriber](https://symfony.com/doc/6.4/service_container/service_subscribers_locators.html#defining-a-service-subscriber).

DoctrineBridge
--------------

 * Deprecate `DbalLogger`, use a middleware instead
 * Deprecate not constructing `DoctrineDataCollector` with an instance of `DebugDataHolder`
 * Deprecate `DoctrineDataCollector::addLogger()`, use a `DebugDataHolder` instead
 * Deprecate `ContainerAwareLoader`, use dependency injection in your fixtures instead

ErrorHandler
------------

 * [BC break] `FlattenExceptionNormalizer` no longer implements `ContextAwareNormalizerInterface`

Form
----

 * Deprecate using `DateTime` or `DateTimeImmutable` model data with a different timezone than configured with the
   `model_timezone` option in `DateType`, `DateTimeType`, and `TimeType`
 * Deprecate `PostSetDataEvent::setData()`, use `PreSetDataEvent::setData()` instead
 * Deprecate `PostSubmitEvent::setData()`, use `PreSubmitDataEvent::setData()` or `SubmitDataEvent::setData()` instead

FrameworkBundle
---------------

 * [BC break] Add native return type to `Translator` and to `Application::reset()`
 * Deprecate the integration of Doctrine annotations, either uninstall the `doctrine/annotations` package or disable
   the integration by setting `framework.annotations` to `false`

HttpFoundation
--------------

 * [BC break] Make `HeaderBag::getDate()`, `Response::getDate()`, `getExpires()` and `getLastModified()` return a `DateTimeImmutable`

HttpKernel
----------

 * [BC break] `BundleInterface` no longer extends `ContainerAwareInterface`
 * [BC break] Add native return types to `TraceableEventDispatcher` and to `MergeExtensionConfigurationPass`

Messenger
---------

 * Deprecate `StopWorkerOnSignalsListener` in favor of using the `SignalableCommandInterface`

MonologBridge
-------------

 * [BC break] Add native return type to `Logger::clear()` and to `DebugProcessor::clear()`

PsrHttpMessageBridge
--------------------

 * Remove `ArgumentValueResolverInterface` from `PsrServerRequestResolver`

Routing
-------

 * [BC break] Add native return type to `AnnotationClassLoader::setResolver()`
 * Deprecate Doctrine annotations support in favor of native attributes
 * Deprecate passing an annotation reader as first argument to `AnnotationClassLoader` (new signature: `__construct(?string $env = null)`)

Security
--------

 * [BC break] `UserValueResolver` no longer implements `ArgumentValueResolverInterface`
 * [BC break] Make `PersistentToken` immutable
 * Deprecate accepting only `DateTime` for `TokenProviderInterface::updateToken()`, use `DateTimeInterface` instead

SecurityBundle
--------------

 * Deprecate the `require_previous_session` config option. Setting it has no effect anymore

Serializer
----------

 * Deprecate Doctrine annotations support in favor of native attributes
 * Deprecate passing an annotation reader to the constructor of `AnnotationLoader`

Templating
----------

 * The component is deprecated and will be removed in 7.0, use [Twig](https://twig.symfony.com) instead

Validator
---------

 * Deprecate Doctrine annotations support in favor of native attributes
 * Deprecate passing an annotation reader to the constructor signature of `AnnotationLoader`
 * Deprecate `ValidatorBuilder::setDoctrineAnnotationReader()`
 * Deprecate `ValidatorBuilder::addDefaultDoctrineAnnotationReader()`
