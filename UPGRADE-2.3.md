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
