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

TwigBundle
----------

 * Deprecated the public `twig` service to private.

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
