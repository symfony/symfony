UPGRADE FROM 6.3 to 6.4
=======================

Symfony 6.4 and Symfony 7.0 are released simultaneously at the end of November 2023. According to the Symfony
release process, both versions have the same features, but Symfony 6.4 doesn't include any significant backwards
compatibility changes.
Minor backwards compatibility breaks are prefixed in this document with `[BC BREAK]`, make sure your code is compatible
with these entries before upgrading. Read more about this in the [Symfony documentation](https://symfony.com/doc/6.4/setup/upgrade_minor.html).

Furthermore, Symfony 6.4 comes with a set of deprecation notices to help you prepare your code for Symfony 7.0. For the
full set of deprecations, see the `UPGRADE-7.0.md` file on the [7.0 branch](https://github.com/symfony/symfony/blob/7.0/UPGRADE-7.0.md).

Table of Contents
-----------------

Bundles
* [FrameworkBundle](#FrameworkBundle)
* [SecurityBundle](#SecurityBundle)

Bridges
* [DoctrineBridge](#DoctrineBridge)
* [MonologBridge](#MonologBridge)
* [PsrHttpMessageBridge](#PsrHttpMessageBridge)

Components
* [BrowserKit](#BrowserKit)
* [Cache](#Cache)
* [DependencyInjection](#DependencyInjection)
* [DomCrawler](#DomCrawler)
* [ErrorHandler](#ErrorHandler)
* [Form](#Form)
* [HttpFoundation](#HttpFoundation)
* [HttpKernel](#HttpKernel)
* [Messenger](#Messenger)
* [RateLimiter](#RateLimiter)
* [Routing](#Routing)
* [Security](#Security)
* [Serializer](#Serializer)
* [Templating](#Templating)
* [Validator](#Validator)
* [VarExporter](#VarExporter)
* [Workflow](#Workflow)

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

 * [BC Break] Add argument `$buildDir` to `ProxyCacheWarmer::warmUp()` 
 * [BC Break] Add return type-hints to `EntityFactory`
 * Deprecate `DbalLogger`, use a middleware instead
 * Deprecate not constructing `DoctrineDataCollector` with an instance of `DebugDataHolder`
 * Deprecate `DoctrineDataCollector::addLogger()`, use a `DebugDataHolder` instead
 * Deprecate `ContainerAwareLoader`, use dependency injection in your fixtures instead
 * [BC Break] Change argument `$lastUsed` of `DoctrineTokenProvider::updateToken()` to accept `DateTimeInterface`

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
 * Deprecate not setting some config options, their defaults will change in Symfony 7.0:

  | option                                       | default Symfony <7.0       | default in Symfony 7.0+                                                     |
  | -------------------------------------------- | -------------------------- | --------------------------------------------------------------------------- |
  | `framework.http_method_override`             | `true`                     | `false`                                                                     |
  | `framework.handle_all_throwables`            | `false`                    | `true`                                                                      |
  | `framework.php_errors.log`                   | `'%kernel.debug%'`         | `true`                                                                      |
  | `framework.session.cookie_secure`            | `false`                    | `'auto'`                                                                    |
  | `framework.session.cookie_samesite`          | `null`                     | `'lax'`                                                                     |
  | `framework.session.handler_id`               | `'session.handler.native'` | `null` if `save_path` is not set, `'session.handler.native_file'` otherwise |
  | `framework.uid.default_uuid_version`         | `6`                        | `7`                                                                         |
  | `framework.uid.time_based_uuid_version`      | `6`                        | `7`                                                                         |
  | `framework.validation.email_validation_mode` | `'loose'`                  | `'html5'`                                                                   |
 * Deprecate `framework.validation.enable_annotations`, use `framework.validation.enable_attributes` instead
 * Deprecate `framework.serializer.enable_annotations`, use `framework.serializer.enable_attributes` instead
 * Deprecate the `routing.loader.annotation` service, use the `routing.loader.attribute` service instead
 * Deprecate the `routing.loader.annotation.directory` service, use the `routing.loader.attribute.directory` service instead
 * Deprecate the `routing.loader.annotation.file` service, use the `routing.loader.attribute.file` service instead
 * Deprecate `AnnotatedRouteControllerLoader`, use `AttributeRouteControllerLoader` instead

HttpFoundation
--------------

 * [BC break] Make `HeaderBag::getDate()`, `Response::getDate()`, `getExpires()` and `getLastModified()` return a `DateTimeImmutable`

HttpKernel
----------

 * [BC break] `BundleInterface` no longer extends `ContainerAwareInterface`
 * [BC break] Add native return types to `TraceableEventDispatcher` and to `MergeExtensionConfigurationPass`
 * Deprecate `Kernel::stripComments()`
 * Deprecate `UriSigner`, use `UriSigner` from the HttpFoundation component instead
 * Deprecate `FileLinkFormatter`, use `FileLinkFormatter` from the ErrorHandler component instead

Messenger
---------

 * Deprecate `StopWorkerOnSignalsListener` in favor of using the `SignalableCommandInterface`
 * Deprecate `HandlerFailedException::getNestedExceptions()`, `HandlerFailedException::getNestedExceptionsOfClass()` and  `DelayedMessageHandlingException::getExceptions()` which are replaced by a new `getWrappedExceptions()` method

MonologBridge
-------------

 * [BC break] Add native return type to `Logger::clear()` and to `DebugProcessor::clear()`

PsrHttpMessageBridge
--------------------

 * [BC break] `PsrServerRequestResolver` no longer implements `ArgumentValueResolverInterface`

RateLimiter
-----------

 * Deprecate `SlidingWindow::getRetryAfter`, use `SlidingWindow::calculateTimeForTokens` instead

Routing
-------

 * [BC break] Add native return type to `AnnotationClassLoader::setResolver()`
 * Deprecate Doctrine annotations support in favor of native attributes
 * Deprecate passing an annotation reader as first argument to `AnnotationClassLoader` (new signature: `__construct(?string $env = null)`)
 * Deprecate `AnnotationClassLoader`, use `AttributeClassLoader` instead
 * Deprecate `AnnotationDirectoryLoader`, use `AttributeDirectoryLoader` instead
 * Deprecate `AnnotationFileLoader`, use `AttributeFileLoader` instead

Security
--------

 * [BC break] `UserValueResolver` no longer implements `ArgumentValueResolverInterface`
 * [BC break] Make `PersistentToken` immutable
 * Deprecate accepting only `DateTime` for `TokenProviderInterface::updateToken()`, use `DateTimeInterface` instead
 * [BC break] Add required `string $secret` parameter to the constructor of `DefaultLoginRateLimiter`

SecurityBundle
--------------

 * Deprecate the `require_previous_session` config option. Setting it has no effect anymore

Serializer
----------

 * Deprecate Doctrine annotations support in favor of native attributes
 * Deprecate `AnnotationLoader`, use `AttributeLoader` instead

Templating
----------

 * The component is deprecated and will be removed in 7.0, use [Twig](https://twig.symfony.com) instead

Translator
----------

 * [BC Break] Add argument `$buildDir` to `DataCollectorTranslator::warmUp()`

Validator
---------

 * Deprecate Doctrine annotations support in favor of native attributes
 * Deprecate `ValidatorBuilder::setDoctrineAnnotationReader()`
 * Deprecate `ValidatorBuilder::addDefaultDoctrineAnnotationReader()`
 * Deprecate `ValidatorBuilder::enableAnnotationMapping()`, use `ValidatorBuilder::enableAttributeMapping()` instead
 * Deprecate `ValidatorBuilder::disableAnnotationMapping()`, use `ValidatorBuilder::disableAttributeMapping()` instead
 * Deprecate `AnnotationLoader`, use `AttributeLoader` instead

VarExporter
-----------

 * Deprecate per-property lazy-initializers

Workflow
--------

* Deprecate `GuardEvent::getContext()` method that will be removed in 7.0
