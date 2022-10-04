UPGRADE FROM 5.x to 6.0
=======================

Asset
-----

 * Removed `RemoteJsonManifestVersionStrategy`, use `JsonManifestVersionStrategy` instead.

DoctrineBridge
--------------

 * Remove `UserLoaderInterface::loadUserByUsername()` in favor of `UserLoaderInterface::loadUserByIdentifier()`
 * Add argument `$bundleDir` to `AbstractDoctrineExtension::getMappingDriverBundleConfigDefaults()`
 * Add argument `$bundleDir` to `AbstractDoctrineExtension::getMappingResourceConfigDirectory()`

Cache
-----

 * Remove `DoctrineProvider` and `DoctrineAdapter` because these classes have been added to the `doctrine/cache` package
 * `PdoAdapter` does not accept `Doctrine\DBAL\Connection` or DBAL URL. Use the new `DoctrineDbalAdapter` instead

Config
------

 * The signature of method `NodeDefinition::setDeprecated()` has been updated to `NodeDefinition::setDeprecation(string $package, string $version, string $message)`.
 * The signature of method `BaseNode::setDeprecated()` has been updated to `BaseNode::setDeprecation(string $package, string $version, string $message)`.
 * Passing a null message to `BaseNode::setDeprecated()` to un-deprecate a node is not supported anymore.
 * Removed `BaseNode::getDeprecationMessage()`, use `BaseNode::getDeprecation()` instead.

Console
-------

 * `Command::setHidden()` has a default value (`true`) for `$hidden` parameter
 * Remove `Helper::strlen()`, use `Helper::width()` instead.
 * Remove `Helper::strlenWithoutDecoration()`, use `Helper::removeDecoration()` instead.
 * Remove `HelperSet::setCommand()` and `getCommand()` without replacement

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

Finder
------

 * Remove `Comparator::setTarget()` and `Comparator::setOperator()`
 * The `$target` parameter of `Comparator::__construct()` is now mandatory

Form
----

 * `FormErrorIterator::children()` throws an exception if the current element is not iterable.
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

 * Remove the `framework.translator.enabled_locales` config option, use `framework.enabled_locales` instead
 * Remove the `session.storage` alias and `session.storage.*` services, use the `session.storage.factory` alias and `session.storage.factory.*` services instead
 * Remove `framework.session.storage_id` configuration option, use the `framework.session.storage_factory_id` configuration option instead
 * Remove the `session` service and the `SessionInterface` alias, use the `\Symfony\Component\HttpFoundation\Request::getSession()` or the new `\Symfony\Component\HttpFoundation\RequestStack::getSession()` methods instead
 * `MicroKernelTrait::configureRoutes()` is now always called with a `RoutingConfigurator`
 * The "framework.router.utf8" configuration option defaults to `true`
 * Removed `session.attribute_bag` service and `session.flash_bag` service.
 * The `form.factory`, `form.type.file`, `profiler`, `translator`, `security.csrf.token_manager`, `serializer`,
   `cache_clearer`, `filesystem` and `validator` services are now private.
 * Removed the `lock.RESOURCE_NAME` and `lock.RESOURCE_NAME.store` services and the `lock`, `LockInterface`, `lock.store` and `PersistingStoreInterface` aliases, use `lock.RESOURCE_NAME.factory`, `lock.factory` or `LockFactory` instead.
 * Remove the `KernelTestCase::$container` property, use `KernelTestCase::getContainer()` instead
 * Registered workflow services are now private
 * Remove option `--xliff-version` of the `translation:update` command, use e.g. `--output-format=xlf20` instead
 * Remove option `--output-format` of the `translation:update` command, use e.g. `--output-format=xlf20` instead
 * Remove the `AdapterInterface` autowiring alias, use `CacheItemPoolInterface` instead
 * Remove `get()`, `has()`, `getDoctrine()`, and `dispatchMessage()` in `AbstractController`, use method/constructor injection instead
 * Deprecate the `cache.adapter.doctrine` service: The Doctrine Cache library is deprecated. Either switch to Symfony Cache or use the PSR-6 adapters provided by Doctrine Cache.
 * Make the `framework.messenger.reset_on_message` configuration option default to `true`
 * In `framework.cache` configuration, using the `cache.adapter.pdo` with a Doctrine DBAL connection is no longer supported, use `cache.adapter.doctrine_dbal` instead.

HttpFoundation
--------------

 * Remove the `NamespacedAttributeBag` class
 * Removed `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, `StreamedResponse::create()` and
   `BinaryFileResponse::create()` methods (use `__construct()` instead)
 * Not passing a `Closure` together with `FILTER_CALLBACK` to `ParameterBag::filter()` throws an `\InvalidArgumentException`; wrap your filter in a closure instead
 * Not passing a `Closure` together with `FILTER_CALLBACK` to `InputBag::filter()` throws an `\InvalidArgumentException`; wrap your filter in a closure instead
 * Removed the `Request::HEADER_X_FORWARDED_ALL` constant, use either `Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO` or `Request::HEADER_X_FORWARDED_AWS_ELB` or `Request::HEADER_X_FORWARDED_TRAEFIK`constants instead
 * Rename `RequestStack::getMasterRequest()` to `getMainRequest()`
 * Not passing `FILTER_REQUIRE_ARRAY` or `FILTER_FORCE_ARRAY` flags to `InputBag::filter()` when filtering an array will throw `BadRequestException`
 * Retrieving non-scalar values using `InputBag::get()` will throw `BadRequestException` (use `InputBag::all()` instead to retrieve an array)
 * Passing non-scalar default value as the second argument `InputBag::get()` will throw `\InvalidArgumentException`
 * Passing non-scalar, non-array value as the second argument `InputBag::set()` will throw `\InvalidArgumentException`
 * Passing `null` as `$requestIp` to `IpUtils::__checkIp()`, `IpUtils::__checkIp4()` or `IpUtils::__checkIp6()` is not supported anymore.
 * Remove the `upload_progress.*` and `url_rewriter.tags` session options

HttpKernel
----------

 * Remove `ArgumentInterface`
 * Remove `ArgumentMetadata::getAttribute()`, use `getAttributes()` instead
 * Make `WarmableInterface::warmUp()` return a list of classes or files to preload on PHP 7.4+
 * Remove support for `service:action` syntax to reference controllers. Use `serviceOrFqcn::method` instead.
 * Remove support for returning a `ContainerBuilder` from `KernelInterface::registerContainerConfiguration()`
 * Rename `HttpKernelInterface::MASTER_REQUEST` to `MAIN_REQUEST`
 * Rename `KernelEvent::isMasterRequest()` to `isMainRequest()`

Inflector
---------

 * The component has been removed, use `EnglishInflector` from the String component instead.

Ldap
----

 * Remove `LdapAuthenticator::createAuthenticatedToken()`, use `LdapAuthenticator::createToken()` instead

Lock
----

 * Removed the `NotSupportedException`. It shouldn't be thrown anymore.
 * Removed the `RetryTillSaveStore`. Logic has been moved in `Lock` and is not needed anymore.
 * Removed usage of `PdoStore` with a `Doctrine\DBAL\Connection` or a DBAL url, use the new `DoctrineDbalStore` instead
 * Removed usage of `PostgreSqlStore` with a `Doctrine\DBAL\Connection` or a DBAL url, use the new `DoctrineDbalPostgreSqlStore` instead

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
 * Removed the use of TLS option for Redis Bridge, use `rediss://127.0.0.1` instead of `redis://127.0.0.1?tls=1`
 * The `delete_after_ack` config option of the Redis transport now defaults to `true`

Mime
----

 * Removed `Address::fromString()`, use `Address::create()` instead

Monolog
-------

 * The `$actionLevel` constructor argument of `Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy` has been replaced by the `$inner` one which expects an ActivationStrategyInterface to decorate instead. `Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy` is now final.
 * The `$actionLevel` constructor argument of `Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy` has been replaced by the `$inner` one which expects an ActivationStrategyInterface to decorate instead. `Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy` is now final.
 * Remove `ResetLoggersWorkerSubscriber` in favor of "reset_on_message" option in messenger configuration

Notifier
--------

 * Remove `SlackOptions::channel()`, use `SlackOptions::recipient()` instead.

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

 * Drop support for booleans as the second argument of `PropertyAccessor::__construct()`, pass a combination of bitwise flags instead.
 * Dropped support for booleans as the first argument of `PropertyAccessor::__construct()`.
   Pass a combination of bitwise flags instead.

PropertyInfo
------------

 * Removed the `Type::getCollectionKeyType()` and `Type::getCollectionValueType()` methods, use `Type::getCollectionKeyTypes()` and `Type::getCollectionValueTypes()` instead.
 * Dropped the `enable_magic_call_extraction` context option in `ReflectionExtractor::getWriteInfo()` and `ReflectionExtractor::getReadInfo()` in favor of `enable_magic_methods_extraction`.

Routing
-------

 * Removed `RouteCollectionBuilder`.
 * Added argument `$priority` to `RouteCollection::add()`
 * Removed the `RouteCompiler::REGEX_DELIMITER` constant
 * Removed the `$data` parameter from the constructor of the `Route` annotation class

Security
--------

 * Remove `AuthenticationEvents::AUTHENTICATION_FAILURE`, use the `LoginFailureEvent` instead
 * Remove the `$authenticationEntryPoint` argument of `ChannelListener`
 * Remove `RetryAuthenticationEntryPoint`, this code was inlined in the `ChannelListener`
 * Remove `FormAuthenticationEntryPoint` and `BasicAuthenticationEntryPoint`, the `FormLoginAuthenticator` and `HttpBasicAuthenticator` should be used instead.
 * Remove `AbstractRememberMeServices`, `PersistentTokenBasedRememberMeServices`, `RememberMeServicesInterface`,
   `TokenBasedRememberMeServices`, use the remember me handler alternatives instead
 * Remove `AnonymousToken`
 * Remove `Token::getCredentials()`, tokens should no longer contain credentials (as they represent authenticated sessions)
 * Restrict the return type of `Token::getUser()` to `UserInterface` (removing `string|\Stringable`)
 * Remove `AuthenticatedVoter::IS_AUTHENTICATED_ANONYMOUSLY` and `AuthenticatedVoter::IS_ANONYMOUS`,
   use `AuthenticatedVoter::PUBLIC_ACCESS` instead.

   Before:
   ```yaml
   # config/packages/security.yaml
   security:
       # ...
       access_control:
           - { path: ^/login, roles: IS_AUTHENTICATED_ANONYMOUSLY }
   ```

   After:
   ```yaml
   # config/packages/security.yaml
   security:
       # ...
       access_control:
           - { path: ^/login, roles: PUBLIC_ACCESS }
   ```

 * Remove `AuthenticationTrustResolverInterface::isAnonymous()` and the `is_anonymous()` expression function
   as anonymous no longer exists in version 6, use the `isFullFledged()` or the new `isAuthenticated()` instead
   if you want to check if the request is (fully) authenticated.
 * Remove the 4th and 5th argument of `AuthorizationChecker`
 * Remove the 5th argument of `AccessListener`
 * Remove class `User`, use `InMemoryUser` or your own implementation instead.
   If you are using the `isAccountNonLocked()`, `isAccountNonExpired()` or `isCredentialsNonExpired()` method, consider re-implementing them
   in your own user class as they are not part of the `InMemoryUser` API
 * Remove class `UserChecker`, use `InMemoryUserChecker` or your own implementation instead
 * Remove `UserInterface::getPassword()`
   If your `getPassword()` method does not return `null` (i.e. you are using password-based authentication),
   you should implement `PasswordAuthenticatedUserInterface`.

   Before:
   ```php
   use Symfony\Component\Security\Core\User\UserInterface;

   class User implements UserInterface
   {
       // ...

       public function getPassword()
       {
           return $this->password;
       }
   }
   ```

   After:
   ```php
   use Symfony\Component\Security\Core\User\UserInterface;
   use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

   class User implements UserInterface, PasswordAuthenticatedUserInterface
   {
       // ...

       public function getPassword(): ?string
       {
           return $this->password;
       }
   }
   ```

 * Remove `UserInterface::getSalt()`
   If your `getSalt()` method does not return `null` (i.e. you are using password-based authentication with an old password hash algorithm that requires user-provided salts),
   implement `LegacyPasswordAuthenticatedUserInterface`.

   Before:
   ```php
   use Symfony\Component\Security\Core\User\UserInterface;

   class User implements UserInterface
   {
       // ...

       public function getPassword()
       {
           return $this->password;
       }

       public function getSalt()
       {
           return $this->salt;
       }
   }
   ```

   After:
   ```php
   use Symfony\Component\Security\Core\User\UserInterface;
   use Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface;

   class User implements UserInterface, LegacyPasswordAuthenticatedUserInterface
   {
       // ...

       public function getPassword(): ?string
       {
           return $this->password;
       }

       public function getSalt(): ?string
       {
           return $this->salt;
       }
   }
   ```

 * Remove `UserInterface::getUsername()` in favor of `UserInterface::getUserIdentifier()`
 * Remove `TokenInterface::getUsername()` in favor of `TokenInterface::getUserIdentifier()`
 * Remove `UserProviderInterface::loadUserByUsername()` in favor of `UserProviderInterface::loadUserByIdentifier()`
 * Remove `UsernameNotFoundException` in favor of `UserNotFoundException` and `getUsername()`/`setUsername()` in favor of `getUserIdentifier()`/`setUserIdentifier()`
 * Remove `PersistentTokenInterface::getUsername()` in favor of `PersistentTokenInterface::getUserIdentifier()`
 * Calling `PasswordUpgraderInterface::upgradePassword()` with a `UserInterface` instance that
   does not implement `PasswordAuthenticatedUserInterface` now throws a `\TypeError`.
 * Calling methods `hashPassword()`, `isPasswordValid()` and `needsRehash()` on `UserPasswordHasherInterface`
   with a `UserInterface` instance that does not implement `PasswordAuthenticatedUserInterface` now throws a `\TypeError`
 * Drop all classes in the `Core\Encoder\`  sub-namespace, use the `PasswordHasher` component instead
 * Drop support for `SessionInterface $session` as constructor argument of `SessionTokenStorage`, inject a `\Symfony\Component\HttpFoundation\RequestStack $requestStack` instead
 * Drop support for `session` provided by the ServiceLocator injected in `UsageTrackingTokenStorage`, provide a `request_stack` service instead
 * Make `SessionTokenStorage` throw a `SessionNotFoundException` when called outside a request context
 * Removed `ROLE_PREVIOUS_ADMIN` role in favor of `IS_IMPERSONATOR` attribute
 * Removed `LogoutSuccessHandlerInterface` and `LogoutHandlerInterface`, register a listener on the `LogoutEvent` event instead.
 * Removed `DefaultLogoutSuccessHandler` in favor of `DefaultLogoutListener`.
 * Added a `logout(Request $request, Response $response, TokenInterface $token)` method to the `RememberMeServicesInterface`.
 * Removed `setProviderKey()`/`getProviderKey()` in favor of `setFirewallName()/getFirewallName()`
   in `PreAuthenticatedToken`, `RememberMeToken`, `SwitchUserToken`, `UsernamePasswordToken`,
   `DefaultAuthenticationSuccessHandler`.
 * Removed the `AbstractRememberMeServices::$providerKey` property in favor of `AbstractRememberMeServices::$firewallName`
 * `AccessDecisionManager` now throw an exception when a voter does not return a valid decision.
 * Remove `AuthenticationManagerInterface`, `AuthenticationProviderManager`, `AnonymousAuthenticationProvider`,
   `AuthenticationProviderInterface`, `DaoAuthenticationProvider`, `LdapBindAuthenticationProvider`,
   `PreAuthenticatedAuthenticationProvider`, `RememberMeAuthenticationProvider`, `UserAuthenticationProvider` and
   `AuthenticationFailureEvent` from security-core, use the new authenticator system instead
 * Remove `AbstractAuthenticationListener`, `AbstractPreAuthenticatedListener`, `AnonymousAuthenticationListener`,
   `BasicAuthenticationListener`, `RememberMeListener`, `RemoteUserAuthenticationListener`,
   `UsernamePasswordFormAuthenticationListener`, `UsernamePasswordJsonAuthenticationListener` and `X509AuthenticationListener`
   from security-http, use the new authenticator system instead
 * Remove the Guard component, use the new authenticator system instead
 * Remove `TokenInterface:isAuthenticated()` and `setAuthenticated()`,
   return `null` from `getUser()` instead when a token is not authenticated
 * Remove `DeauthenticatedEvent`, use `TokenDeauthenticatedEvent` instead
 * Remove `CookieClearingLogoutHandler`, `SessionLogoutHandler` and `CsrfTokenClearingLogoutHandler`.
   Use `CookieClearingLogoutListener`, `SessionLogoutListener` and `CsrfTokenClearingLogoutListener` instead
 * Remove `AuthenticatorInterface::createAuthenticatedToken()`, use `AuthenticatorInterface::createToken()` instead
 * Remove `PassportInterface`, `UserPassportInterface` and `PassportTrait`, use `Passport` instead.
   Also, the return type declaration of `AuthenticatorInterface::authenticate()` was changed to `Passport`

   Before:
   ```php
   class MyAuthenticator implements AuthenticatorInterface
   {
       public function authenticate(Request $request): PassportInterface
       {
       }
   }
   ```

   After:
   ```php
   class MyAuthenticator implements AuthenticatorInterface
   {
       public function authenticate(Request $request): Passport
       {
       }
   }
   ```
 * `AccessDecisionManager` does not accept strings as strategy anymore,
   pass an instance of `AccessDecisionStrategyInterface` instead
 * Removed the `$credentials` argument of `PreAuthenticatedToken`,
   `SwitchUserToken` and `UsernamePasswordToken`:

   Before:
   ```php
   $token = new UsernamePasswordToken($user, $credentials, $firewallName, $roles);
   $token = new PreAuthenticatedToken($user, $credentials, $firewallName, $roles);
   $token = new SwitchUserToken($user, $credentials, $firewallName, $roles, $originalToken);
   ```

   After:
   ```php
   $token = new UsernamePasswordToken($user, $firewallName, $roles);
   $token = new PreAuthenticatedToken($user, $firewallName, $roles);
   $token = new SwitchUserToken($user, $firewallName, $roles, $originalToken);
   ```

SecurityBundle
--------------

 * Remove `FirewallConfig::getListeners()`, use `FirewallConfig::getAuthenticators()` instead
 * Remove `security.authentication.basic_entry_point` and `security.authentication.retry_entry_point` services,
   the logic is moved into the `HttpBasicAuthenticator` and `ChannelListener` respectively
 * Remove `SecurityFactoryInterface` and `SecurityExtension::addSecurityListenerFactory()` in favor of
   `AuthenticatorFactoryInterface` and `SecurityExtension::addAuthenticatorFactory()`
 * Add `AuthenticatorFactoryInterface::getPriority()` which replaces `SecurityFactoryInterface::getPosition()`.
   Previous positions are mapped to the following priorities:

    | Position    | Constant                                              | Priority |
    | ----------- | ----------------------------------------------------- | -------- |
    | pre_auth    | `RemoteUserFactory::PRIORITY`/`X509Factory::PRIORITY` | -10      |
    | form        | `FormLoginFactory::PRIORITY`                          | -30      |
    | http        | `HttpBasicFactory::PRIORITY`                          | -50      |
    | remember_me | `RememberMeFactory::PRIORITY`                         | -60      |
    | anonymous   | n/a                                                   | -70      |

 * Remove passing an array of arrays as 1st argument to `MainConfiguration`, pass a sorted flat array of
   factories instead.
 * Remove the `always_authenticate_before_granting` option
 * Remove the `UserPasswordEncoderCommand` class and the corresponding `user:encode-password` command,
   use `UserPasswordHashCommand` and `user:hash-password` instead
 * Remove the `security.encoder_factory.generic` service, the `security.encoder_factory` and `Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface` aliases,
   use `security.password_hasher_factory` and `Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface` instead
 * Remove the `security.user_password_encoder.generic` service, the `security.password_encoder` and the `Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface` aliases,
   use `security.user_password_hasher`, `security.password_hasher` and `Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface` instead
 * The `security.authorization_checker` and `security.token_storage` services are now private
 * Not setting the `enable_authenticator_manager` option to `true` now throws an exception
 * Remove the `security.authentication.provider.*` services, use the new authenticator system instead
 * Remove the `security.authentication.listener.*` services, use the new authenticator system instead
 * Remove the Guard component integration, use the new authenticator system instead
 * Remove the default provider for custom_authenticators when there is more than one registered provider

Serializer
----------

 * Removed `ArrayDenormalizer::setSerializer()`, call `setDenormalizer()` instead.
 * `ArrayDenormalizer` does not implement `SerializerAwareInterface` anymore.
 * The annotation classes cannot be constructed by passing an array of parameters as first argument anymore, use named arguments instead

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
  $builder
      ->enableAnnotationMapping()
      ->setDoctrineAnnotationReader($reader);
  ```

 * `ValidatorBuilder::enableAnnotationMapping()` won't automatically setup a Doctrine annotation reader anymore.

  Before:

  ```php
  $builder->enableAnnotationMapping();
  ```

  After:

  ```php
  $builder
      ->enableAnnotationMapping()
      ->addDefaultDoctrineAnnotationReader();
  ```

Workflow
--------

 * Remove `InvalidTokenConfigurationException`

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
