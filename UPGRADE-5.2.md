UPGRADE FROM 5.1 to 5.2
=======================

DependencyInjection
-------------------

 * Deprecated `Definition::setPrivate()` and `Alias::setPrivate()`, use `setPublic()` instead

Mime
----

 * Deprecated `Address::fromString()`, use `Address::create()` instead

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
