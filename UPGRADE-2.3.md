UPGRADE FROM 2.2 to 2.3
=======================

### Form

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

### PropertyAccess

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
