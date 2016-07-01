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
   
 * Setting the strict option of the `Choice` Constraint to `false` has been  
   deprecated and the option will be changed to `true` as of 4.0.

   ```php
   // ...
   use Symfony\Component\Validator\Constraints as Assert;

   class MyEntity
   {
       /**
        * @Assert\Choice(choices={"MR", "MRS"}, strict=true)
        */
       private $salutation;
   }
   ```
