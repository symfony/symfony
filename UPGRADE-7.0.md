UPGRADE FROM 6.4 to 7.0
=======================

Symfony 6.4 and Symfony 7.0 are released simultaneously at the end of November 2023. According to the Symfony
release process, both versions have the same features, but Symfony 7.0 doesn't include any deprecated features.
To upgrade, make sure to resolve all deprecation notices.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.0/setup/upgrade_major.html).

Symfony 7.0 introduced many native return and property types. Read [the announcement blogpost](https://symfony.com/blog/symfony-7-0-type-declarations)
on how to quickly make your code compatible.

Table of Contents
-----------------

Bundles
 * [FrameworkBundle](#FrameworkBundle)
 * [SecurityBundle](#SecurityBundle)
 * [TwigBundle](#TwigBundle)

Bridges
 * [DoctrineBridge](#DoctrineBridge)
 * [MonologBridge](#MonologBridge)
 * [ProxyManagerBridge](#ProxyManagerBridge)

Components
 * [Cache](#Cache)
 * [Config](#Config)
 * [Console](#Console)
 * [DependencyInjection](#DependencyInjection)
 * [DomCrawler](#DomCrawler)
 * [ExpressionLanguage](#ExpressionLanguage)
 * [Filesystem](#Filesystem)
 * [Form](#Form)
 * [HttpFoundation](#HttpFoundation)
 * [HttpClient](#HttpClient)
 * [HttpKernel](#HttpKernel)
 * [Lock](#Lock)
 * [Mailer](#Mailer)
 * [Messenger](#Messenger)
 * [Mime](#Mime)
 * [PropertyAccess](#PropertyAccess)
 * [Routing](#Routing)
 * [Security](#Security)
 * [Serializer](#Serializer)
 * [Templating](#Templating)
 * [Translation](#Translation)
 * [Validator](#Validator)
 * [VarDumper](#VarDumper)
 * [VarExporter](#VarExporter)
 * [Workflow](#Workflow)
 * [Yaml](#Yaml)

Cache
-----

 * Add parameter `\Closure $isSameDatabase` to `DoctrineDbalAdapter::configureSchema()`
 * Drop support for Postgres < 9.5 and SQL Server < 2008 in `DoctrineDbalAdapter`

Config
------

 * Require explicit argument when calling `NodeBuilder::setParent()`

Console
-------

 * Remove `Command::$defaultName` and `Command::$defaultDescription`, use the `AsCommand` attribute instead

   *Before*
   ```php
   use Symfony\Component\Console\Command\Command;

   class CreateUserCommand extends Command
   {
       protected static $defaultName = 'app:create-user';
       protected static $defaultDescription = 'Creates users';

       // ...
   }
   ```

   *After*
   ```php
   use Symfony\Component\Console\Attribute\AsCommand;
   use Symfony\Component\Console\Command\Command;

   #[AsCommand(name: 'app:create-user', description: 'Creates users')]
   class CreateUserCommand extends Command
   {
       // ...
   }
   ```

 * Require explicit argument when calling `*Command::setApplication()`, `*FormatterStyle::setForeground/setBackground()`, `Helper::setHelpSet()`, `Input*::setDefault()` and `Question::setAutocompleterCallback/setValidator()`
 * Remove `StringInput::REGEX_STRING`, use `StringInput::REGEX_UNQUOTED_STRING` or `StringInput::REGEX_QUOTED_STRING` instead
 * Add method `__toString()` to `InputInterface`

DependencyInjection
-------------------

 * Rename `#[MapDecorated]` to `#[AutowireDecorated]`
 * Remove `ProxyHelper`, use `Symfony\Component\VarExporter\ProxyHelper` instead
 * Remove `ReferenceSetArgumentTrait`
 * Remove support of `@required` annotation, use the `Symfony\Contracts\Service\Attribute\Required` attribute instead
 * Require explicit argument when calling `ContainerAwareTrait::setContainer()`
 * Remove `PhpDumper` options `inline_factories_parameter` and `inline_class_loader_parameter`, use options `inline_factories` and `inline_class_loader` with the direct boolean value instead
 * Parameter names of `ParameterBag` cannot be numerics
 * Remove `ContainerAwareInterface` and `ContainerAwareTrait`, use dependency injection instead

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
 * Add parameter `string $id = null` and `bool &$asGhostObject = null` to `LazyProxy\PhpDumper\DumperInterface::isProxyCandidate()` and `getProxyCode()`
 * Add parameter `string $source = null` to `FileLoader::registerClasses()`

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
 * Add parameter `\Closure $isSameDatabase` to `DoctrineTokenProvider::configureSchema()`
 * Remove support for Doctrine subscribers in `ContainerAwareEventManager`, use listeners instead

   *Before*
   ```php
   use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
   use Doctrine\ORM\Event\PostFlushEventArgs;
   use Doctrine\ORM\Events;

   class InvalidateCacheSubscriber implements EventSubscriberInterface
   {
        public function getSubscribedEvents(): array
        {
            return [Events::postFlush];
        }

        public function postFlush(PostFlushEventArgs $args): void
        {
            // ...
        }
   }
   ```

   *After*
   ```php
   use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
   use Doctrine\ORM\Event\PostFlushEventArgs;
   use Doctrine\ORM\Events;

   // Instead of PHP attributes, you can also tag this service with "doctrine.event_listener"
   #[AsDoctrineListener(event: Events::postFlush)]
   class InvalidateCacheSubscriber
   {
        public function postFlush(PostFlushEventArgs $args): void
        {
            // ...
        }
   }
   ```

DomCrawler
----------

 * Add parameter `bool $normalizeWhitespace = true` to `Crawler::innerText()`
 * Add parameter `string $default = null` to `Crawler::attr()`

ExpressionLanguage
------------------

 * The `in` and `not in` operators now use strict comparison

Filesystem
----------

 * Add parameter `bool $lock = false` to `Filesystem::appendToFile()`

Form
----

 * Throw when using `DateTime` or `DateTimeImmutable` model data with a different timezone than configured with the `model_timezone` option in `DateType`, `DateTimeType`, and `TimeType`
 * Make the "widget" option of date/time form types default to "single_text"
 * Require explicit argument when calling `Button/Form::setParent()`, `ButtonBuilder/FormConfigBuilder::setDataMapper()`, `TransformationFailedException::setInvalidMessage()`
 * `PostSetDataEvent::setData()` throws an exception, use `PreSetDataEvent::setData()` instead
 * `PostSubmitEvent::setData()` throws an exception, use `PreSubmitDataEvent::setData()` or `SubmitDataEvent::setData()` instead
 * Add `$duplicatePreferredChoices` parameter to `ChoiceListFactoryInterface::createView()`

FrameworkBundle
---------------

 * Renamed command `translation:update` to `translation:extract`
 * Remove the `Symfony\Component\Serializer\Normalizer\ObjectNormalizer` and
   `Symfony\Component\Serializer\Normalizer\PropertyNormalizer` autowiring aliases, type-hint against
   `Symfony\Component\Serializer\Normalizer\NormalizerInterface` or implement `NormalizerAwareInterface` instead
 * Remove the `Http\Client\HttpClient` service, use `Psr\Http\Client\ClientInterface` instead
 * Remove `AbstractController::renderForm()`, pass the `FormInterface` as parameter to `render()`

   *Before*
   ```php
   $this->renderForm(..., ['form' => $form]);
   ```

   *After*
   ```php
   $this->render(..., ['form' => $form]);
   ```

 * Remove the integration of the Doctrine annotations library, use native attributes instead
 * Remove `EnableLoggerDebugModePass`, use argument `$debug` of HttpKernel's `Logger` instead
 * Remove `AddDebugLogProcessorPass::configureLogger()`, use HttpKernel's `DebugLoggerConfigurator` instead
 * Add `array $tokenAttributes = []` optional parameter to `KernelBrowser::loginUser()`
 * Change default of some config options:

   | option                                       | default Symfony <7.0       | default in Symfony 7.0+                                                     |
   |----------------------------------------------|----------------------------|-----------------------------------------------------------------------------|
   | `framework.http_method_override`             | `true`                     | `false`                                                                     |
   | `framework.handle_all_throwables`            | `false`                    | `true`                                                                      |
   | `framework.php_errors.log`                   | `'%kernel.debug%'`         | `true`                                                                      |
   | `framework.session.cookie_secure`            | `false`                    | `auto`                                                                      |
   | `framework.session.cookie_samesite`          | `null`                     | `'lax'`                                                                     |
   | `framework.session.handler_id`               | `'session.handler.native'` | `null` if `save_path` is not set, `'session.handler.native_file'` otherwise |
   | `framework.uid.default_uuid_version`         | `6`                        | `7`                                                                         |
   | `framework.uid.time_based_uuid_version`      | `6`                        | `7`                                                                         |
   | `framework.validation.email_validation_mode` | `'loose'`                  | `'html5'`                                                                   |
 * Remove the `framework.validation.enable_annotations` config option, use `framework.validation.enable_attributes` instead
 * Remove the `framework.serializer.enable_annotations` config option, use `framework.serializer.enable_attributes` instead
 * Remove the `routing.loader.annotation` service, use the `routing.loader.attribute` service instead
 * Remove the `routing.loader.annotation.directory` service, use the `routing.loader.attribute.directory` service instead
 * Remove the `routing.loader.annotation.file` service, use the `routing.loader.attribute.file` service instead
 * Remove `AnnotatedRouteControllerLoader`, use `AttributeRouteControllerLoader` instead
 * Remove `AddExpressionLanguageProvidersPass`, use `Symfony\Component\Routing\DependencyInjection\AddExpressionLanguageProvidersPass` instead
 * Remove `DataCollectorTranslatorPass`, use `Symfony\Component\Translation\DependencyInjection\DataCollectorTranslatorPass` instead
 * Remove `LoggingTranslatorPass`, use `Symfony\Component\Translation\DependencyInjection\LoggingTranslatorPass` instead
 * Remove `WorkflowGuardListenerPass`, use `Symfony\Component\Workflow\DependencyInjection\WorkflowGuardListenerPass` instead

HttpFoundation
--------------

 * Calling `ParameterBag::filter()` on an invalid value throws an `UnexpectedValueException` instead of returning `false`.
   The exception is more specific for `InputBag` which throws a `BadRequestException` when invalid value is found.
   The flag `FILTER_NULL_ON_FAILURE` can be used to return `null` instead of throwing an exception.
 * The methods `ParameterBag::getInt()` and `ParameterBag::getBool()` no longer fallback to `0` or `false`
   when the value cannot be converted to the expected type. They throw a `UnexpectedValueException` instead.
 * Remove `RequestMatcher`, use `ChainRequestMatcher` instead
 * Remove `ExpressionRequestMatcher`, use `RequestMatcher\ExpressionRequestMatcher` instead
 * Rename `Request::getContentType()` to `Request::getContentTypeFormat()`
 * Throw an `InvalidArgumentException` when calling `Request::create()` with a malformed URI
 * Require explicit argument when calling `JsonResponse::setCallback()`, `Response::setExpires/setLastModified/setEtag()`, `MockArraySessionStorage/NativeSessionStorage::setMetadataBag()`, `NativeSessionStorage::setSaveHandler()`
 * Add parameter `int $statusCode = null` to `Response::sendHeaders()` and `StreamedResponse::sendHeaders()`

HttpClient
----------

 * Remove implementing `Http\Message\RequestFactory` from `HttplugClient`

HttpKernel
----------

 * Add parameter `\ReflectionFunctionAbstract $reflector = null` to `ArgumentResolverInterface::getArguments()` and `ArgumentMetadataFactoryInterface::createArgumentMetadata()`
 * Add argument `$buildDir` to `WarmableInterface`
 * Remove `ArgumentValueResolverInterface`, use `ValueResolverInterface` instead
 * Remove `StreamedResponseListener`
 * Remove `AbstractSurrogate::$phpEscapeMap`
 * Rename `HttpKernelInterface::MASTER_REQUEST` to `HttpKernelInterface::MAIN_REQUEST`
 * Remove `terminate_on_cache_hit` option from `HttpCache`, it will now always act as `false`
 * Require explicit argument when calling `ConfigDataCollector::setKernel()`, `RouterListener::setCurrentRequest()`
 * Remove `FileLinkFormatter`, use `FileLinkFormatter` from the ErrorHandler component instead
 * Remove `UriSigner`, use `UriSigner` from the HttpFoundation component instead
 * Remove `Kernel::stripComments()`
 * Add argument `$filter` to `Profiler::find()` and `FileProfilerStorage::find()`

Lock
----

 * Add parameter `\Closure $isSameDatabase` to `DoctrineDbalStore::configureSchema()`
 * Rename `gcProbablity` (notice the typo) option to `gcProbability` in the `MongoDbStore`

Mailer
------

 * Remove the OhMySmtp bridge in favor of the MailPace bridge

Messenger
---------

 * Add parameter `\Closure $isSameDatabase` to `DoctrineTransport::configureSchema()`
 * Remove `MessageHandlerInterface` and `MessageSubscriberInterface`, use `#[AsMessageHandler]` instead

   *Before*
   ```php
   use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
   use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

   class SmsNotificationHandler implements MessageHandlerInterface
   {
       public function __invoke(SmsNotification $message): void
       {
           // ...
       }
   }

   class UploadedImageHandler implements MessageSubscriberInterface
   {
       public static function getHandledMessages(): iterable
       {
           yield ThumbnailUploadedImage::class => ['method' => 'handleThumbnail'];
           yield ProfilePictureUploadedImage::class => ['method' => 'handleProfilePicture'];
       }

       // ...
   }
   ```

   *After*
   ```php
   use Symfony\Component\Messenger\Attribute\AsMessageHandler;

   #[AsMessageHandler]
   class SmsNotificationHandler
   {
       public function __invoke(SmsNotification $message): void
       {
           // ...
       }
   }

   class UploadedImageHandler
   {
       #[AsMessageHandler]
       public function handleThumbnail(ThumbnailUploadedImage $message): void
       {
           // ...
       }

       #[AsMessageHandler]
       public function handleThumbnail(ProfilePictureUploadedImage $message): void
       {
           // ...
       }
   }
   ```
 * Remove `StopWorkerOnSigtermSignalListener` in favor of using the `SignalableCommandInterface`
 * Remove `StopWorkerOnSignalsListener` in favor of using the `SignalableCommandInterface`
 * Rename `Symfony\Component\Messenger\Transport\InMemoryTransport` and `Symfony\Component\Messenger\Transport\InMemoryTransportFactory` to
   `Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport` and `Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory` respectively
 * Remove `HandlerFailedException::getNestedExceptions()`, `HandlerFailedException::getNestedExceptionsOfClass()`
   and `DelayedMessageHandlingException::getExceptions()` which are replaced by a new `getWrappedExceptions()` method

Mime
----

 * Remove `Email::attachPart()` method, use `Email::addPart()` instead
 * Require explicit argument when calling `Message::setBody()`

MonologBridge
-------------

 * Drop support for monolog < 3.0
 * Remove class `Logger`, use HttpKernel's `DebugLoggerConfigurator` instead

PropertyAccess
--------------

 * Add method `isNullSafe()` to `PropertyPathInterface`
 * Require explicit argument when calling `PropertyAccessorBuilder::setCacheItemPool()`

ProxyManagerBridge
------------------

 * Remove the bridge, use VarExporter's lazy objects instead

Routing
-------

 * Add parameter `array $routeParameters` to `UrlMatcher::handleRouteRequirements()`
 * Remove Doctrine annotations support in favor of native attributes. Use `Symfony\Component\Routing\Annotation\Route` as native attribute now
 * Remove `AnnotationClassLoader`, use `AttributeClassLoader` instead
 * Remove `AnnotationDirectoryLoader`, use `AttributeDirectoryLoader` instead
 * Remove `AnnotationFileLoader`, use `AttributeFileLoader` instead

Security
--------

 * Add parameter `string $badgeFqcn = null` to `Passport::addBadge()`
 * Add parameter `int $lifetime = null` to `LoginLinkHandlerInterface::createLoginLink()`
 * Require explicit argument when calling `TokenStorage::setToken()`
 * Change argument `$lastUsed` of `TokenProviderInterface::updateToken()` to accept `DateTimeInterface`
 * Throw when calling the constructor of `DefaultLoginRateLimiter` with an empty secret

SecurityBundle
--------------

 * Enabling SecurityBundle and not configuring it is not allowed, either remove the bundle or configure at least one firewall
 * Remove the `enable_authenticator_manager` config option
 * Remove the `security.firewalls.logout.csrf_token_generator` config option, use `security.firewalls.logout.csrf_token_manager` instead
 * Remove the `require_previous_session` config option from authenticators

Serializer
----------

 * Add method `getSupportedTypes()` to `DenormalizerInterface` and `NormalizerInterface`
 * Remove denormalization support for `AbstractUid` in `UidNormalizer`, use one of `AbstractUid` child class instead
 * Denormalizing to an abstract class in `UidNormalizer` now throws an `\Error`
 * Remove `ContextAwareDenormalizerInterface` and `ContextAwareNormalizerInterface`, use `DenormalizerInterface` and `NormalizerInterface` instead

   *Before*
   ```php
   use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;

   class TopicNormalizer implements ContextAwareNormalizerInterface
   {
       public function normalize($topic, string $format = null, array $context = [])
       {
       }
   }
   ```

   *After*
   ```php
   use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

   class TopicNormalizer implements NormalizerInterface
   {
       public function normalize($topic, string $format = null, array $context = [])
       {
       }
   }
   ```

 * Remove `CacheableSupportsMethodInterface`, use `NormalizerInterface` and `DenormalizerInterface` instead

   *Before*
   ```php
   use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
   use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;

   class TopicNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
   {
       public function supportsNormalization($data, string $format = null, array $context = []): bool
       {
           return $data instanceof Topic;
       }

       public function hasCacheableSupportsMethod(): bool
       {
           return true;
       }

       // ...
   }
   ```

   *After*
   ```php
   use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

   class TopicNormalizer implements NormalizerInterface
   {
       public function supportsNormalization($data, string $format = null, array $context = []): bool
       {
           return $data instanceof Topic;
       }

       public function getSupportedTypes(?string $format): array
       {
           return [
               Topic::class => true,
           ];
       }

       // ...
   }
   ```

 * Require explicit argument when calling `AttributeMetadata::setSerializedName()` and `ClassMetadata::setClassDiscriminatorMapping()`
 * Add parameter `array $context = []` to `NormalizerInterface::supportsNormalization()` and `DenormalizerInterface::supportsDenormalization()`
 * Remove Doctrine annotations support in favor of native attributes
 * Remove the annotation reader parameter from the constructor of `AnnotationLoader`
 * The following Normalizer classes have become final, use decoration instead of inheritance:
   * `ConstraintViolationListNormalizer`
   * `CustomNormalizer`
   * `DataUriNormalizer`
   * `DateIntervalNormalizer`
   * `DateTimeNormalizer`
   * `DateTimeZoneNormalizer`
   * `GetSetMethodNormalizer`
   * `JsonSerializableNormalizer`
   * `ObjectNormalizer`
   * `PropertyNormalizer`

   *Before*
   ```php
   use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

   class TopicNormalizer extends ObjectNormalizer
   {
       // ...

       public function normalize($topic, string $format = null, array $context = []): array
       {
           $data = parent::normalize($topic, $format, $context);

           // ...
       }
   }
   ```

   *After*
   ```php
   use Symfony\Component\DependencyInjection\Attribute\Autowire;
   use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
   use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

   class TopicNormalizer implements NormalizerInterface
   {
       public function __construct(
           #[Autowire(service: 'serializer.normalizer.object')] private NormalizerInterface&DenormalizerInterface $objectNormalizer,
       ) {
       }

       public function normalize($topic, string $format = null, array $context = []): array
       {
           $data = $this->objectNormalizer->normalize($topic, $format, $context);

           // ...
       }

       // ...
   }
   ```
 * Remove `AnnotationLoader`, use `AttributeLoader` instead

Templating
----------

 * Remove the component; use [Twig](https://twig.symfony.com) instead

Translation
-----------

 * Remove `PhpStringTokenParser`
 * Remove `PhpExtractor` in favor of `PhpAstExtractor`

TwigBundle
----------

 * Remove the `Twig_Environment` autowiring alias, use `Twig\Environment` instead
 * Remove option `twig.autoescape`; create a class that implements your escaping strategy
   (check `FileExtensionEscapingStrategy::guess()` for inspiration) and reference it using
   the `twig.autoescape_service` option instead
 * Drop support for Twig 2

Validator
---------

 * Add methods `getConstraint()`, `getCause()` and `__toString()` to `ConstraintViolationInterface`
 * Add method `__toString()` to `ConstraintViolationListInterface`
 * Add method `disableTranslation()` to `ConstraintViolationBuilderInterface`
 * Remove static property `$errorNames` from all constraints, use const `ERROR_NAMES` instead
 * Remove static property `$versions` from the `Ip` constraint, use the `VERSIONS` constant instead
 * Remove `VALIDATION_MODE_LOOSE` from `Email` constraint, use `VALIDATION_MODE_HTML5` instead
 * Remove constraint `ExpressionLanguageSyntax`, use `ExpressionSyntax` instead. The new constraint is ignored when the value
   is null or blank, consistently with the other constraints in this component
 * Remove Doctrine annotations support in favor of native attributes
 * Remove `ValidatorBuilder::setDoctrineAnnotationReader()`
 * Remove `ValidatorBuilder::addDefaultDoctrineAnnotationReader()`
 * Remove `ValidatorBuilder::enableAnnotationMapping()`, use `ValidatorBuilder::enableAttributeMapping()` instead
 * Remove `ValidatorBuilder::disableAnnotationMapping()`, use `ValidatorBuilder::disableAttributeMapping()` instead
 * Remove `AnnotationLoader`, use `AttributeLoader` instead

VarDumper
---------

 * Add parameter `string $label = null` to `VarDumper::dump()`
 * Require explicit argument when calling `VarDumper::setHandler()`

VarExporter
-----------

 * Remove support for per-property lazy-initializers

Workflow
--------

 * Require explicit argument when calling `Definition::setInitialPlaces()`
 * `GuardEvent::getContext()` method has been removed. Method was not supposed to be called within guard event listeners as it always returned an empty array anyway.

Yaml
----

 * Remove the `!php/const:` tag, use `!php/const` instead (without the colon)
