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
 * Add `ExtensionInterface::build(ContainerBuilder $container): void` to register compiler passes from an extension

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

DomCrawler
----------

 * Add argument `$default` to `Crawler::attr()`

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
 * Deprecate not setting the `framework.handle_all_throwables` config option; it will default to `true` in 7.0
 * Deprecate not setting the `framework.php_errors.log` config option; it will default to `true` in 7.0
 * Deprecate not setting the `framework.session.cookie_secure` config option; it will default to `auto` in 7.0
 * Deprecate not setting the `framework.session.cookie_samesite` config option; it will default to `lax` in 7.0
 * Deprecate not setting either `framework.session.handler_id` or `save_path` config options; `handler_id` will
   default to null in 7.0 if `save_path` is not set and to `session.handler.native_file` otherwise
 * Deprecate not setting the `framework.uid.default_uuid_version` config option; it will default to `7` in 7.0
 * Deprecate not setting the `framework.uid.time_based_uuid_version` config option; it will default to `7` in 7.0
 * Deprecate not setting the `framework.validation.email_validation_mode` config option; it will default to `html5` in 7.0
 * Deprecate `framework.validation.enable_annotations`, use `framework.validation.enable_attributes` instead
 * Deprecate `framework.serializer.enable_annotations`, use `framework.serializer.enable_attributes` instead

HttpFoundation
--------------

 * [BC break] Make `HeaderBag::getDate()`, `Response::getDate()`, `getExpires()` and `getLastModified()` return a `DateTimeImmutable`

HttpKernel
----------

 * [BC break] `BundleInterface` no longer extends `ContainerAwareInterface`
 * [BC break] Add native return types to `TraceableEventDispatcher` and to `MergeExtensionConfigurationPass`
 * Deprecate `Kernel::stripComments()`

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
 * Deprecate `ValidatorBuilder::setDoctrineAnnotationReader()`
 * Deprecate `ValidatorBuilder::addDefaultDoctrineAnnotationReader()`
 * Deprecate `ValidatorBuilder::enableAnnotationMapping()`, use `ValidatorBuilder::enableAttributeMapping()` instead
 * Deprecate `ValidatorBuilder::disableAnnotationMapping()`, use `ValidatorBuilder::disableAttributeMapping()` instead
 * Deprecate `AnnotationLoader`, use `AttributeLoader` instead

Workflow
--------

* Deprecate `GuardEvent::getContext()` method that will be removed in 7.0
