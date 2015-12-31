UPGRADE FROM 3.1 to 3.2
=======================

DependencyInjection
-------------------

 * Calling `get()` on a `ContainerBuilder` instance before compiling the
   container is deprecated and will throw an exception in Symfony 4.0.

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
