UPGRADE FROM 6.2 to 6.3
=======================

Cache
-----

 * `DoctrineDbalAdapter` now takes an optional `$isSameDatabase` parameter

Console
-------

 * Return int or false from `SignalableCommandInterface::handleSignal()` instead
   of void and add a second argument `$previousExitCode`

DependencyInjection
-------------------

 * Deprecate `PhpDumper` options `inline_factories_parameter` and `inline_class_loader_parameter`, use `inline_factories` and `inline_class_loader` instead
 * Deprecate undefined and numeric keys with `service_locator` config, use string aliases instead
 * Deprecate `#[MapDecorated]`, use `#[AutowireDecorated]` instead
 * Deprecate the `@required` annotation, use the `Symfony\Contracts\Service\Attribute\Required` attribute instead

DoctrineBridge
--------------

 * Deprecate passing Doctrine subscribers to `ContainerAwareEventManager` class, use listeners instead

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

 * Deprecate `DoctrineDbalCacheAdapterSchemaSubscriber` in favor of `DoctrineDbalCacheAdapterSchemaListener`
 * Deprecate `MessengerTransportDoctrineSchemaSubscriber` in favor of `MessengerTransportDoctrineSchemaListener`
 * Deprecate `RememberMeTokenProviderDoctrineSchemaSubscriber` in favor of `RememberMeTokenProviderDoctrineSchemaListener`
 * `DoctrineTransport` now takes an optional `$isSameDatabase` parameter
 * `DoctrineTokenProvider` now takes an optional `$isSameDatabase` parameter

Form
----

 * Deprecate not configuring the "widget" option of date/time form types, it will default to "single_text" in v7

FrameworkBundle
---------------

 * Deprecate `framework:exceptions` tag, unwrap it and replace `framework:exception` tags' `name` attribute by `class`

   Before:
   ```xml
   <!-- config/packages/framework.xml -->
   <framework:config>
       <framework:exceptions>
           <framework:exception
               name="Symfony\Component\HttpKernel\Exception\BadRequestHttpException"
               log-level="info"
               status-code="422"
           />
       </framework:exceptions>
   </framework:config>
   ```

   After:
   ```xml
   <!-- config/packages/framework.xml -->
   <framework:config>
       <framework:exception
           class="Symfony\Component\HttpKernel\Exception\BadRequestHttpException"
           log-level="info"
           status-code="422"
       />
   </framework:config>
   ```
 * Deprecate the `notifier.logger_notification_listener` service, use the `notifier.notification_logger_listener` service instead
 * Deprecate the `Http\Client\HttpClient` service, use `Psr\Http\Client\ClientInterface` instead

HttpClient
----------

 * The minimum TLS version now defaults to v1.2; use the `crypto_method`
   option if you need to connect to servers that don't support it
 * The default user agents have been renamed from `Symfony HttpClient/Amp`, `Symfony HttpClient/Curl`
   and `Symfony HttpClient/Native` to `Symfony HttpClient (Amp)`, `Symfony HttpClient (Curl)`
   and `Symfony HttpClient (Native)` respectively to comply with the RFC 9110 specification

HttpFoundation
--------------

 * `Response::sendHeaders()` now takes an optional `$statusCode` parameter
 * Deprecate conversion of invalid values in `ParameterBag::getInt()` and `ParameterBag::getBoolean()`
 * Deprecate ignoring invalid values when using `ParameterBag::filter()`, unless flag `FILTER_NULL_ON_FAILURE` is set

HttpKernel
----------

 * Deprecate parameters `container.dumper.inline_factories` and `container.dumper.inline_class_loader`, use `.container.dumper.inline_factories` and `.container.dumper.inline_class_loader` instead

Lock
----

 * Deprecate the `gcProbablity` option to fix a typo in its name, use the `gcProbability` option instead
 * Add optional parameter `$isSameDatabase` to `DoctrineDbalStore::configureSchema()`

Messenger
---------

 * Deprecate `Symfony\Component\Messenger\Transport\InMemoryTransport` and
   `Symfony\Component\Messenger\Transport\InMemoryTransportFactory` in favor of
   `Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport` and
   `Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory`
 * Deprecate `StopWorkerOnSigtermSignalListener` in favor of `StopWorkerOnSignalsListener`

Notifier
--------

 * [BC BREAK] The following data providers for `TransportTestCase` are now static: `toStringProvider()`, `supportedMessagesProvider()` and `unsupportedMessagesProvider()`
 * [BC BREAK] The `TransportTestCase::createTransport()` method is now static

Security
--------

 * Deprecate passing a secret as the 2nd argument to the constructor of `Symfony\Component\Security\Http\RememberMe\PersistentRememberMeHandler`

SecurityBundle
--------------

 * Deprecate enabling bundle and not configuring it, either remove the bundle or configure at least one firewall
 * Deprecate the `security.firewalls.logout.csrf_token_generator` config option, use `security.firewalls.logout.csrf_token_manager` instead

Serializer
----------

 * Deprecate `CacheableSupportsMethodInterface` in favor of the new `getSupportedTypes(?string $format)` methods

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
 * The following Normalizer classes will become final in 7.0, use decoration instead of inheritance:
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

Validator
---------

 * Implementing the `ConstraintViolationInterface` without implementing the `getConstraint()` method is deprecated
