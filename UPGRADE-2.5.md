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
