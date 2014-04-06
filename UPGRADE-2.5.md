UPGRADE FROM 2.4 to 2.5
=======================

Routing
-------

 * Added a new optional parameter `$requiredSchemes` to `Symfony\Component\Routing\Generator\UrlGenerator::doGenerate()`

Form
----

 * The method `FormInterface::getErrors()` now returns an instance of
   `Symfony\Component\Form\FormErrorIterator` instead of an array. This object
   is traversable, countable and supports array access. However, you can not
   pass it to any of PHP's `array_*` functions anymore. You should use
   `iterator_to_array()` in those cases where you did.

   Before:

   ```
   $errors = array_map($callback, $form->getErrors());
   ```

   After:

   ```
   $errors = array_map($callback, iterator_to_array($form->getErrors()));
   ```

 * The method `FormInterface::getErrors()` now has two additional, optional
   parameters. Make sure to add these parameters to the method signatures of
   your implementations of that interface.

   Before:

   ```
   public function getErrors()
   {
   ```

   After:

   ```
   public function getErrors($deep = false, $flatten = true)
   {
   ```

PropertyAccess
--------------

 * The methods `isReadable()` and `isWritable()` were added to
   `PropertyAccessorInterface`. If you implemented this interface in your own
   code, you should add these two methods.

 * The methods `getValue()` and `setValue()` now throw an
   `NoSuchIndexException` instead of a `NoSuchPropertyException` when an index
   is accessed on an object that does not implement `ArrayAccess`. If you catch
   this exception in your code, you should adapt the catch statement:

   Before:

   ```php
   $object = new \stdClass();

   try {
       $propertyAccessor->getValue($object, '[index]');
       $propertyAccessor->setValue($object, '[index]', 'New value');
   } catch (NoSuchPropertyException $e) {
       // ...
   }
   ```

   After:

   ```php
   $object = new \stdClass();

   try {
       $propertyAccessor->getValue($object, '[index]');
       $propertyAccessor->setValue($object, '[index]', 'New value');
   } catch (NoSuchIndexException $e) {
       // ...
   }
   ```

   A `NoSuchPropertyException` is still thrown when a non-existing property is
   accessed on an object or an array.

Validator
---------

 * EmailValidator has changed to allow `non-strict` and `strict` email validation

   Before:

   Email validation was done with php's `filter_var()`

   After:

   Default email validation is now done via a simple regex which may cause invalid emails (not RFC compilant) to be
   valid. This is the default behaviour.

   Strict email validation has to be explicitly activated in the configuration file by adding

   ```
   framework:
      //...
      validation:
          strict_email: true
      //...

   ```

   Also you have to add to your composer.json:

   ```
   "egulias/email-validator": "1.1.*"
   ```

 * `ClassMetadata::getGroupSequence()` now returns `GroupSequence` instances
   instead of an array. The sequence implements `\Traversable`, `\ArrayAccess`
   and `\Countable`, so in most cases you should be fine. If you however use the
   sequence with PHP's `array_*()` functions, you should cast it to an array
   first using `iterator_to_array()`:

   Before:

   ```
   $sequence = $metadata->getGroupSequence();
   $result = array_map($callback, $sequence);
   ```

   After:

   ```
   $sequence = iterator_to_array($metadata->getGroupSequence());
   $result = array_map($callback, $sequence);
   ```

 * The array type hint in `ClassMetadata::setGroupSequence()` was removed. If
   you overwrite this method, make sure to remove the type hint as well. The
   method should now accept `GroupSequence` instances just as well as arrays.

   Before:

   ```
   public function setGroupSequence(array $groups)
   {
       // ...
   }
   ```

   After:

   ```
   public function setGroupSequence($groupSequence)
   {
       // ...
   }
   ```

 * The validation engine in `Symfony\Component\Validator\Validator` was replaced
   by a new one in `Symfony\Component\Validator\Validator\RecursiveValidator`.
   With that change, several classes were deprecated that will be removed in
   Symfony 3.0. Also, the API of the validator was slightly changed. More
   details about that can be found in UPGRADE-3.0.

   You can choose the desired API via the new "api" entry in
   app/config/config.yml:

   ```
   framework:
       validation:
           enabled: true
           api: auto
   ```

   When running PHP 5.3.9 or higher, Symfony will then use an implementation
   that supports both the old API and the new one:

   ```
   framework:
       validation:
           enabled: true
           api: 2.5-bc
   ```

   When running PHP lower than 5.3.9, that compatibility layer is not supported.
   On those versions, the old implementation will be used instead:

   ```
   framework:
       validation:
           enabled: true
           api: 2.4
   ```

   If you develop a new application that doesn't rely on the old API, you can
   also set the API to 2.5. In that case, the backwards compatibility layer
   will not be activated:

   ```
   framework:
       validation:
           enabled: true
           api: 2.5
   ```

   When using the validator outside of the Symfony full-stack framework, the
   desired API can be selected using `setApiVersion()` on the validator builder:

   ```
   // Previous implementation
   $validator = Validation::createValidatorBuilder()
       ->setApiVersion(Validation::API_VERSION_2_4)
       ->getValidator();

   // New implementation with backwards compatibility support
   $validator = Validation::createValidatorBuilder()
       ->setApiVersion(Validation::API_VERSION_2_5_BC)
       ->getValidator();

   // New implementation without backwards compatibility support
   $validator = Validation::createValidatorBuilder()
       ->setApiVersion(Validation::API_VERSION_2_5)
       ->getValidator();
   ```

