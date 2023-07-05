UPGRADE FROM 6.4 to 7.0
=======================

Symfony 6.4 and Symfony 7.0 will be released simultaneously at the end of November 2023. According to the Symfony
release process, both versions will have the same features, but Symfony 7.0 won't include any deprecated features.
To upgrade, make sure to resolve all deprecation notices.
Read more about this in the [Symfony documentation](https://symfony.com/doc/current/setup/upgrade_major.html).

Cache
-----

 * Add parameter `$isSameDatabase` to `DoctrineDbalAdapter::configureSchema()`

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
 * Remove `StringInput::REGEX_STRING`
 * Add method `__toString()` to `InputInterface`

DependencyInjection
-------------------

 * Remove `#[MapDecorated]`, use `#[AutowireDecorated]` instead
 * Remove `ProxyHelper`, use `Symfony\Component\VarExporter\ProxyHelper` instead
 * Remove `ReferenceSetArgumentTrait`
 * Remove support of `@required` annotation, use the `Symfony\Contracts\Service\Attribute\Required` attribute instead
 * Require explicit argument when calling `ContainerAwareTrait::setContainer()`
 * Remove `PhpDumper` options `inline_factories_parameter` and `inline_class_loader_parameter`, use options `inline_factories` and `inline_class_loader` instead
 * Parameter names of `ParameterBag` cannot be numerics
 * Remove `ContainerAwareInterface` and `ContainerAwareTrait`, use dependency injection instead
 * Add argument `$id` and `$asGhostObject` to `DumperInterface::isProxyCandidate()` and `getProxyCode()`
 * Add argument `$source` to `FileLoader::registerClasses()`

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

ExpressionLanguage
------------------

 * The `in` and `not in` operators now use strict comparison

Filesystem
----------

 * Add argument `$lock` to `Filesystem::appendToFile()`

Form
----

 * Throw when using `DateTime` or `DateTimeImmutable` model data with a different timezone than configured with the
   `model_timezone` option in `DateType`, `DateTimeType`, and `TimeType`
 * Make the "widget" option of date/time form types default to "single_text"
 * Require explicit argument when calling `Button/Form::setParent()`, `ButtonBuilder/FormConfigBuilder::setDataMapper()`, `TransformationFailedException::setInvalidMessage()`

FrameworkBundle
---------------

 * Remove command `translation:update`, use `translation:extract` instead
 * Make the `http_method_override` config option default to `false`
 * Remove the `Symfony\Component\Serializer\Normalizer\ObjectNormalizer` and
   `Symfony\Component\Serializer\Normalizer\PropertyNormalizer` autowiring aliases, type-hint against
   `Symfony\Component\Serializer\Normalizer\NormalizerInterface` or implement `NormalizerAwareInterface` instead
 * Remove the `Http\Client\HttpClient` service, use `Psr\Http\Client\ClientInterface` instead
 * Remove `AbstractController::renderForm()`, use `render()` instead

   *Before*
   ```php
   $this->renderForm(..., ['form' => $form]);
   ```

   *After*
   ```php
   $this->render(..., ['form' => $form]);
   ```

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
 * Require explicit argument when calling `JsonResponse::setCallback()`, `Response::setExpires/setLastModified/setEtag()`, `MockArraySessionStorage/NativeSessionStorage::setMetadataBag()`, `NativeSessionStorage::setSaveHandler()`

HttpClient
----------

 * Remove implementing `Http\Message\RequestFactory` from `HttplugClient`

HttpKernel
----------

 * Add argument `$reflector` to `ArgumentResolverInterface::getArguments()` and `ArgumentMetadataFactoryInterface::createArgumentMetadata()`
 * Remove `ArgumentValueResolverInterface`, use `ValueResolverInterface` instead
 * Remove `StreamedResponseListener`
 * Remove `AbstractSurrogate::$phpEscapeMap`
 * Remove `HttpKernelInterface::MASTER_REQUEST`
 * Remove `terminate_on_cache_hit` option from `HttpCache`
 * Require explicit argument when calling `ConfigDataCollector::setKernel()`, `RouterListener::setCurrentRequest()`

Lock
----

 * Add parameter `$isSameDatabase` to `DoctrineDbalStore::configureSchema()`
 * Remove the `gcProbablity` (notice the typo) option, use `gcProbability` instead

Mailer
------

 * Remove the OhMySmtp bridge in favor of the MailPace bridge

Messenger
---------

 * Add parameter `$isSameDatabase` to `DoctrineTransport::configureSchema()`
 * Remove `MessageHandlerInterface` and `MessageSubscriberInterface`, use `#[AsMessageHandler]` instead
 * Remove `StopWorkerOnSigtermSignalListener` in favor of
   `StopWorkerOnSignalsListener` and make it configurable with SIGINT and
 * Remove `Symfony\Component\Messenger\Transport\InMemoryTransport` and
   `Symfony\Component\Messenger\Transport\InMemoryTransportFactory` in favor of
   `Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport` and
   `Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory`

Mime
----

 * Remove `Email::attachPart()` method, use `Email::addPart()` instead
 * Require explicit argument when calling `Message::setBody()`

PropertyAccess
--------------

 * Add method `isNullSafe()` to `PropertyPathInterface`
 * Require explicit argument when calling `PropertyAccessorBuilder::setCacheItemPool()`

ProxyManagerBridge
------------------

 * Remove the bridge, use VarExporter's lazy objects instead

Routing
-------

 * Add argument `$routeParameters` to `UrlMatcher::handleRouteRequirements()`

Security
--------

 * Add argument `$badgeFqcn` to `Passport::addBadge()`
 * Add argument `$lifetime` to `LoginLinkHandlerInterface::createLoginLink()`
 * Require explicit argument when calling `TokenStorage::setToken()`

SecurityBundle
--------------

 * Enabling SecurityBundle and not configuring it is not allowed, either remove the bundle or configure at least one firewall

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
 * Add argument `$context` to `NormalizerInterface::supportsNormalization()` and `DenormalizerInterface::supportsDenormalization()`

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

Validator
---------

 * Add methods `getConstraint()`, `getCause()` and `__toString()` to `ConstraintViolationInterface`
 * Add method `__toString()` to `ConstraintViolationListInterface`
 * Add method `disableTranslation()` to `ConstraintViolationBuilderInterface`
 * Remove static property `$errorNames` from all constraints, use const `ERROR_NAMES` instead
 * Remove `VALIDATION_MODE_LOOSE` from `Email` constraint, use `VALIDATION_MODE_HTML5` instead
 * Remove constraint `ExpressionLanguageSyntax`, use `ExpressionSyntax` instead

VarDumper
---------

 * Add argument `$label` to `VarDumper::dump()`
 * Require explicit argument when calling `VarDumper::setHandler()`

Workflow
--------

 * Require explicit argument when calling `Definition::setInitialPlaces()`

Yaml
----

 * Remove the `!php/const:` tag, use `!php/const` instead (without the colon)
