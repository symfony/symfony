UPGRADE FROM 5.2 to 5.3
=======================

Asset
-----

 * Deprecated `RemoteJsonManifestVersionStrategy`, use `JsonManifestVersionStrategy` instead

Console
-------

 * Deprecate `Helper::strlen()`, use `Helper::width()` instead.
 * Deprecate `Helper::strlenWithoutDecoration()`, use `Helper::removeDecoration()` instead.

DoctrineBridge
--------------

 * Deprecate `UserLoaderInterface::loadUserByUsername()` in favor of `UserLoaderInterface::loadUserByIdentifier()`
 * Remove `UuidV*Generator` classes

DomCrawler
----------

 * Deprecated the `parents()` method, use `ancestors()` instead

Form
----

 * Changed `$forms` parameter type of the `DataMapperInterface::mapDataToForms()` method from `iterable` to `\Traversable`
 * Changed `$forms` parameter type of the `DataMapperInterface::mapFormsToData()` method from `iterable` to `\Traversable`
 * Deprecated passing an array as the second argument of the `DataMapper::mapDataToForms()` method, pass `\Traversable` instead
 * Deprecated passing an array as the first argument of the `DataMapper::mapFormsToData()` method, pass `\Traversable` instead
 * Deprecated passing an array as the second argument of the `CheckboxListMapper::mapDataToForms()` method, pass `\Traversable` instead
 * Deprecated passing an array as the first argument of the `CheckboxListMapper::mapFormsToData()` method, pass `\Traversable` instead
 * Deprecated passing an array as the second argument of the `RadioListMapper::mapDataToForms()` method, pass `\Traversable` instead
 * Deprecated passing an array as the first argument of the `RadioListMapper::mapFormsToData()` method, pass `\Traversable` instead
 * Dependency on `symfony/intl` was removed. Install `symfony/intl` if you are using `LocaleType`, `CountryType`, `CurrencyType`, `LanguageType` or `TimezoneType`

FrameworkBundle
---------------

 * Deprecate the `session.storage` alias and `session.storage.*` services, use the `session.storage.factory` alias and `session.storage.factory.*` services instead
 * Deprecate the `framework.session.storage_id` configuration option, use the `framework.session.storage_factory_id` configuration option instead
 * Deprecate the `session` service and the `SessionInterface` alias, use the `\Symfony\Component\HttpFoundation\Request::getSession()` or the new `\Symfony\Component\HttpFoundation\RequestStack::getSession()` methods instead
 * Deprecate the `KernelTestCase::$container` property, use `KernelTestCase::getContainer()` instead
 * Rename the container parameter `profiler_listener.only_master_requests` to `profiler_listener.only_main_requests`
 * Deprecate registering workflow services as public
 * Deprecate option `--xliff-version` of the `translation:update` command, use e.g. `--format=xlf20` instead
 * Deprecate option `--output-format` of the `translation:update` command, use e.g. `--format=xlf20` instead

HttpFoundation
--------------

 * Deprecate the `NamespacedAttributeBag` class
 * Deprecate the `RequestStack::getMasterRequest()` method and add `getMainRequest()` as replacement

HttpKernel
----------

 * Deprecate `ArgumentInterface`
 * Deprecate `ArgumentMetadata::getAttribute()`, use `getAttributes()` instead
 * Mark the class `Symfony\Component\HttpKernel\EventListener\DebugHandlersListener` as internal
 * Deprecate returning a `ContainerBuilder` from `KernelInterface::registerContainerConfiguration()`
 * Deprecate `HttpKernelInterface::MASTER_REQUEST` and add `HttpKernelInterface::MAIN_REQUEST` as replacement
 * Deprecate `KernelEvent::isMasterRequest()` and add `isMainRequest()` as replacement

Messenger
---------

 * Deprecated the `prefetch_count` parameter in the AMQP bridge, it has no effect and will be removed in Symfony 6.0
 * Deprecated the use of TLS option for Redis Bridge, use `rediss://127.0.0.1` instead of `redis://127.0.0.1?tls=1`

Mime
----

 * Remove the internal annotation from the `getHeaderBody()` and `getHeaderParameter()` methods of the `Headers` class.

Notifier
--------

 * Changed the return type of `AbstractTransportFactory::getEndpoint()` from `?string` to `string`
 * Changed the signature of `Dsn::__construct()` to accept a single `string $dsn` argument
 * Removed the `Dsn::fromString()` method


PhpunitBridge
-------------

 * Deprecated the `SetUpTearDownTrait` trait, use original methods with "void" return typehint

PropertyAccess
--------------

* Deprecate passing a boolean as the second argument of `PropertyAccessor::__construct()`, pass a combination of bitwise flags instead.

PropertyInfo
------------

 * Deprecated the `Type::getCollectionKeyType()` and `Type::getCollectionValueType()` methods, use `Type::getCollectionKeyTypes()` and `Type::getCollectionValueTypes()` instead

Routing
-------

 * Deprecate creating instances of the `Route` annotation class by passing an array of parameters, use named arguments instead

Security
--------

 * [BC BREAK] Remove method `checkIfCompletelyResolved()` from `PassportInterface`, checking that passport badges are
   resolved is up to `AuthenticatorManager`
 * Deprecate class `User`, use `InMemoryUser` or your own implementation instead.
   If you are using the `isAccountNonLocked()`, `isAccountNonExpired()` or `isCredentialsNonExpired()` method, consider re-implementing
   them in your own user class, as they are not part of the `InMemoryUser` API
 * Deprecate class `UserChecker`, use `InMemoryUserChecker` or your own implementation instead
 * [BC break] Remove support for passing a `UserInterface` implementation to `Passport`, use the `UserBadge` instead.
 * Deprecate `UserInterface::getPassword()`
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

 * Deprecate `UserInterface::getSalt()`
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

 * Deprecate `UserInterface::getUsername()` in favor of `UserInterface::getUserIdentifier()`
 * Deprecate `TokenInterface::getUsername()` in favor of `TokenInterface::getUserIdentifier()`
 * Deprecate `UserProviderInterface::loadUserByUsername()` in favor of `UserProviderInterface::loadUserByIdentifier()`
 * Deprecate `UsernameNotFoundException` in favor of `UserNotFoundException` and `getUsername()`/`setUsername()` in favor of `getUserIdentifier()`/`setUserIdentifier()`
 * Deprecate `PersistentTokenInterface::getUsername()` in favor of `PersistentTokenInterface::getUserIdentifier()`
 * Deprecate calling `PasswordUpgraderInterface::upgradePassword()` with a `UserInterface` instance that does not implement `PasswordAuthenticatedUserInterface`
 * Deprecate calling methods `hashPassword()`, `isPasswordValid()` and `needsRehash()` on `UserPasswordHasherInterface` with a `UserInterface` instance that does not implement `PasswordAuthenticatedUserInterface`
 * Deprecate all classes in the `Core\Encoder\`  sub-namespace, use the `PasswordHasher` component instead
 * Deprecated voters that do not return a valid decision when calling the `vote` method
 * [BC break] Add optional array argument `$badges` to `UserAuthenticatorInterface::authenticateUser()`
 * Deprecate `AuthenticationManagerInterface`, `AuthenticationProviderManager`, `AnonymousAuthenticationProvider`,
   `AuthenticationProviderInterface`, `DaoAuthenticationProvider`, `LdapBindAuthenticationProvider`,
   `PreAuthenticatedAuthenticationProvider`, `RememberMeAuthenticationProvider`, `UserAuthenticationProvider` and
   `AuthenticationFailureEvent` from security-core, use the new authenticator system instead
 * Deprecate `AbstractAuthenticationListener`, `AbstractPreAuthenticatedListener`, `AnonymousAuthenticationListener`,
   `BasicAuthenticationListener`, `RememberMeListener`, `RemoteUserAuthenticationListener`,
   `UsernamePasswordFormAuthenticationListener`, `UsernamePasswordJsonAuthenticationListener` and `X509AuthenticationListener`
   from security-http, use the new authenticator system instead
 * Deprecate the Guard component, use the new authenticator system instead

SecurityBundle
--------------

 * [BC break] Add `login_throttling.lock_factory` setting defaulting to `null`. Set this option
   to `lock.factory` if you need precise login rate limiting with synchronous requests.
 * Deprecate `UserPasswordEncoderCommand` class and the corresponding `user:encode-password` command,
   use `UserPasswordHashCommand` and `user:hash-password` instead
 * Deprecate the `security.encoder_factory.generic` service, the `security.encoder_factory` and `Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface` aliases,
   use `security.password_hasher_factory` and `Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface` instead
 * Deprecate the `security.user_password_encoder.generic` service, the `security.password_encoder` and the `Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface` aliases,
   use `security.user_password_hasher`, `security.password_hasher` and `Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface` instead
 * Deprecate the public `security.authorization_checker` and `security.token_storage` services to private
 * Not setting the `enable_authenticator_manager` config option to `true` is deprecated
 * Deprecate the `security.authentication.provider.*` services, use the new authenticator system instead
 * Deprecate the `security.authentication.listener.*` services, use the new authenticator system instead
 * Deprecate the Guard component integration, use the new authenticator system instead

Serializer
----------

 * Deprecate `ArrayDenormalizer::setSerializer()`, call `setDenormalizer()` instead
 * Deprecate creating instances of the annotation classes by passing an array of parameters, use named arguments instead

Uid
---

 * Replaced `UuidV1::getTime()`, `UuidV6::getTime()` and `Ulid::getTime()` by `UuidV1::getDateTime()`, `UuidV6::getDateTime()` and `Ulid::getDateTime()`

Workflow
--------

 * Deprecate `InvalidTokenConfigurationException`
