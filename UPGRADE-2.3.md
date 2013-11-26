UPGRADE FROM 2.2 to 2.3
=======================

Form
----

 * Although this was not officially supported nor documented, it was possible to
   set the option "validation_groups" to false, resulting in the group "Default"
   being validated. Now, if you set "validation_groups" to false, the validation
   of a form will be skipped (except for a few integrity checks on the form).

   If you want to validate a form in group "Default", you should either
   explicitly set "validation_groups" to "Default" or alternatively set it to
   null.

   Before:

   ```
   // equivalent notations for validating in group "Default"
   "validation_groups" => null
   "validation_groups" => "Default"
   "validation_groups" => false

   // notation for skipping validation
   "validation_groups" => array()
   ```

   After:

   ```
   // equivalent notations for validating in group "Default"
   "validation_groups" => null
   "validation_groups" => "Default"

   // equivalent notations for skipping validation
   "validation_groups" => false
   "validation_groups" => array()
   ```
 * The array type hint from DataMapperInterface was removed. You should adapt
   implementations of that interface accordingly.

   Before:

   ```
   use Symfony\Component\Form\DataMapperInterface;

   class MyDataMapper
   {
       public function mapFormsToData(array $forms, $data)
       {
           // ...
       }

       public function mapDataToForms($data, array $forms)
       {
           // ...
       }
   }
   ```

   After:

   ```
   use Symfony\Component\Form\DataMapperInterface;

   class MyDataMapper
   {
       public function mapFormsToData($forms, $data)
       {
           // ...
       }

       public function mapDataToForms($data, $forms)
       {
           // ...
       }
   }
   ```

   Instead of an array, the methods here are now passed a
   RecursiveIteratorIterator containing an InheritDataAwareIterator by default,
   so you don't need to handle forms inheriting their parent data (former
   "virtual forms") in the data mapper anymore.

   Before:

   ```
   use Symfony\Component\Form\Util\VirtualFormAwareIterator;

   public function mapFormsToData(array $forms, $data)
   {
       $iterator = new \RecursiveIteratorIterator(
           new VirtualFormAwareIterator($forms)
       );

       foreach ($iterator as $form) {
           // ...
       }
   }
   ```

   After:

   ```
   public function mapFormsToData($forms, $data)
   {
       foreach ($forms as $form) {
           // ...
       }
   }
   ```

 * The *_SET_DATA events are now guaranteed to be fired *after* the children
   were added by the FormBuilder (unless setData() is called manually). Before,
   the *_SET_DATA events were sometimes thrown before adding child forms,
   which made it impossible to remove child forms dynamically.

   A consequence of this change is that you need to set the "auto_initialize"
   option to `false` for `FormInterface` instances that you pass to
   `FormInterface::add()`:

   Before:

   ```
   $form = $factory->create('form');
   $form->add($factory->createNamed('field', 'text'));
   ```

   This code will now throw an exception with the following message:

   Automatic initialization is only supported on root forms. You should set the
   "auto_initialize" option to false on the field "field".

   Consequently, you need to set the "auto_initialize" option:

   After (Alternative 1):

   ```
   $form = $factory->create('form');
   $form->add($factory->createNamed('field', 'text', array(), array(
       'auto_initialize' => false,
   )));
   ```

   The problem also disappears if you work with `FormBuilder` instances instead
   of `Form` instances:

   After (Alternative 2):

   ```
   $builder = $factory->createBuilder('form');
   $builder->add($factory->createBuilder('field', 'text'));
   $form = $builder->getForm();
   ```

   The best solution is in most cases to let `add()` handle the field creation:

   After (Alternative 3):

   ```
   $form = $factory->create('form');
   $form->add('field', 'text');
   ```

   After (Alternative 4):

   ```
   $builder = $factory->createBuilder('form');
   $builder->add('field', 'text');
   $form = $builder->getForm();
   ```

PropertyAccess
--------------

 * PropertyAccessor was changed to continue its search for a property or method
   even if a non-public match was found. This means that the property "author"
   in the following class will now correctly be found:

   ```
   class Article
   {
       public $author;

       private function getAuthor()
       {
           // ...
       }
   }
   ```

   Although this is uncommon, similar cases exist in practice.

   Instead of the PropertyAccessDeniedException that was thrown here, the more
   generic NoSuchPropertyException is thrown now if no public property nor
   method are found by the PropertyAccessor. PropertyAccessDeniedException was
   removed completely.

   Before:

   ```
   use Symfony\Component\PropertyAccess\Exception\PropertyAccessDeniedException;
   use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

   try {
       $value = $accessor->getValue($article, 'author');
   } catch (PropertyAccessDeniedException $e) {
       // Method/property was found but not public
   } catch (NoSuchPropertyException $e) {
       // Method/property was not found
   }
   ```

   After:

   ```
   use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

   try {
       $value = $accessor->getValue($article, 'author');
   } catch (NoSuchPropertyException $e) {
       // Method/property was not found or not public
   }
   ```

DomCrawler
----------

 * `Crawler::each()` and `Crawler::reduce()` now return Crawler instances
   instead of DomElement instances:

   Before:

   ```
   $data = $crawler->each(function ($node, $i) {
       return $node->nodeValue;
   });
   ```

   After:

   ```
   $data = $crawler->each(function ($crawler, $i) {
       return $crawler->text();
   });
   ```

Console
-------

 * New verbosity levels have been added, therefore if you used to do check
   the output verbosity level directly for VERBOSITY_VERBOSE you probably
   want to update it to a greater than comparison:

   Before:

   ```
   if (OutputInterface::VERBOSITY_VERBOSE === $output->getVerbosity()) { ... }
   ```

   After:

   ```
   if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) { ... }
   ```

BrowserKit
----------

 * If you are receiving responses with non-3xx Status Code and Location header
   please be aware that you won't be able to use auto-redirects on these kind
   of responses.

   If you are correctly passing 3xx Status Code with Location header, you
   don't have to worry about the change.

   If you were using responses with Location header and non-3xx Status Code,
   you have to update your code to manually create another request to URL
   grabbed from the Location header.
