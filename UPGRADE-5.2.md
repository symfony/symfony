UPGRADE FROM 5.1 to 5.2
=======================

DependencyInjection
-------------------

 * Deprecated `Definition::setPrivate()` and `Alias::setPrivate()`, use `setPublic()` instead

FrameworkBundle
---------------

 * Deprecated the public `form.factory`, `form.type.file`, `translator`, `security.csrf.token_manager`, `serializer`,
   `cache_clearer`, `filesystem` and `validator` services to private.
 * If you configured the `framework.cache.prefix_seed` option, you might want to add the `%kernel.environment%` to its value to
   keep cache namespaces separated by environment of the app. The `%kernel.container_class%` (which includes the environment)
   used to be added by default to the seed, which is not the case anymore. This allows sharing caches between
   apps or different environments.
 * Deprecated the `lock.RESOURCE_NAME` and `lock.RESOURCE_NAME.store` services and the `lock`, `LockInterface`, `lock.store` and `PersistingStoreInterface` aliases, use `lock.RESOURCE_NAME.factory`, `lock.factory` or `LockFactory` instead.

Form
----

 * Deprecated `PropertyPathMapper` in favor of `DataMapper` and `PropertyPathAccessor`.

 Before:

 ```php
 use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;

 $builder->setDataMapper(new PropertyPathMapper());
 ```

 After:

 ```php
 use Symfony\Component\Form\Extension\Core\DataAccessor\PropertyPathAccessor;
 use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;

 $builder->setDataMapper(new DataMapper(new PropertyPathAccessor()));
 ```

Lock
----

 * `MongoDbStore` does not implement `BlockingStoreInterface` anymore, typehint against `PersistingStoreInterface` instead.
 * deprecated `NotSupportedException`, it shouldn't be thrown anymore.
 * deprecated `RetryTillSaveStore`, logic has been moved in `Lock` and is not needed anymore.

Mime
----

 * Deprecated `Address::fromString()`, use `Address::create()` instead

Monolog
-------

 * The `$actionLevel` constructor argument of `Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy` has been deprecated and replaced by the `$inner` one which expects an ActivationStrategyInterface to decorate instead. `Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy` will become final in 6.0.
 * The `$actionLevel` constructor argument of `Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy` has been deprecated and replaced by the `$inner` one which expects an ActivationStrategyInterface to decorate instead. `Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy` will become final in 6.0

PropertyAccess
--------------

 * Deprecated passing a boolean as the first argument of `PropertyAccessor::__construct()`.
   Pass a combination of bitwise flags instead.

PropertyInfo
------------

 * Deprecated the `enable_magic_call_extraction` context option in `ReflectionExtractor::getWriteInfo()` and `ReflectionExtractor::getReadInfo()` in favor of `enable_magic_methods_extraction`.

TwigBundle
----------

 * Deprecated the public `twig` service to private.

TwigBridge
----------

 * Changed 2nd argument type of `TranslationExtension::__construct()` to `TranslationNodeVisitor`

Validator
---------

 * Deprecated the `allowEmptyString` option of the `Length` constraint.

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

 * Deprecated the `NumberConstraintTrait` trait.

Security
--------

 * [BC break] In the experimental authenticator-based system, * `TokenInterface::getUser()`
   returns `null` in case of unauthenticated session.

 * [BC break] `AccessListener::PUBLIC_ACCESS` has been removed in favor of
   `AuthenticatedVoter::PUBLIC_ACCESS`.

 * Deprecated `setProviderKey()`/`getProviderKey()` in favor of `setFirewallName()/getFirewallName()`
   in `PreAuthenticatedToken`, `RememberMeToken`, `SwitchUserToken`, `UsernamePasswordToken`,
   `DefaultAuthenticationSuccessHandler`, the old methods will be removed in 6.0.

 * Deprecated the `AbstractRememberMeServices::$providerKey` property in favor of
   `AbstractRememberMeServices::$firewallName`, the old property will be removed
   in 6.0.

