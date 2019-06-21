UPGRADE FROM 5.1 to 5.2
=======================

DependencyInjection
-------------------

 * Deprecated `Definition::setPrivate()` and `Alias::setPrivate()`, use `setPublic()` instead

FrameworkBundle
---------------

 * Deprecated the public `form.factory`, `form.type.file`, `translator`, `security.csrf.token_manager`, `serializer`,
   `cache_clearer`, `filesystem` and `validator` services to private.

Mime
----

 * Deprecated `Address::fromString()`, use `Address::create()` instead

PropertyAccess
--------------

 * Deprecated passing a boolean as the first argument of `PropertyAccessor::__construct()`.
   Pass a combination of bitwise flags instead.

PropertyInfo
------------

 * Dropped the `enable_magic_call_extraction` context option in `ReflectionExtractor::getWriteInfo()` and `ReflectionExtractor::getReadInfo()` in favor of `enable_magic_methods_extraction`.

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

Security
--------

 * [BC break] In the experimental authenticator-based system, * `TokenInterface::getUser()`
   returns `null` in case of unauthenticated session.

 * [BC break] `AccessListener::PUBLIC_ACCESS` has been removed in favor of
   `AuthenticatedVoter::PUBLIC_ACCESS`.
