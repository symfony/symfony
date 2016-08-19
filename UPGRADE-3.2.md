UPGRADE FROM 3.1 to 3.2
=======================

FrameworkBundle
---------------

 * The `Controller::getUser()` method has been deprecated and will be removed in
   Symfony 4.0; typehint the security user object in the action instead.

DependencyInjection
-------------------

 * Calling `get()` on a `ContainerBuilder` instance before compiling the
   container is deprecated and will throw an exception in Symfony 4.0.

Form
----

 * Calling `isValid()` on a `Form` instance before submitting it
   is deprecated and will throw an exception in Symfony 4.0.

   Before:

   ```php
   if ($form->isValid()) {
       // ...
   }
   ```

   After:

   ```php
   if ($form->isSubmitted() && $form->isValid()) {
       // ...
   }
   ```

FrameworkBundle
---------------

  * The service `serializer.mapping.cache.doctrine.apc` is deprecated. APCu should now
    be automatically used when available.

Validator
---------

 * `Tests\Constraints\AbstractConstraintValidatorTest` has been deprecated in
   favor of `Test\ConstraintValidatorTestCase`.

   Before:

   ```php
   // ...
   use Symfony\Component\Validator\Tests\Constraints\AbstractConstraintValidatorTest;

   class MyCustomValidatorTest extends AbstractConstraintValidatorTest
   {
       // ...
   }
   ```

   After:

   ```php
   // ...
   use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

   class MyCustomValidatorTest extends ConstraintValidatorTestCase
   {
       // ...
   }
   ```

Yaml
----

 * Support for silently ignoring duplicate keys in YAML has been deprecated and
   will lead to a `ParseException` in Symfony 4.0.

 * Mappings with a colon that is not followed by a space are deprecated and
   will lead to a `ParseException` in Symfony 4.0.
