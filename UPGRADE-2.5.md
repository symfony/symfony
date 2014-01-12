UPGRADE FROM 2.4 to 2.5
=======================

Routing
-------

 * Added a new optional parameter `$requiredSchemes` to `Symfony\Component\Routing\Generator\UrlGenerator::doGenerate()`

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
   framework_bundle:
      //...
      validation:
          strict_email: true
      //...

   ```
   Also you have to add to your composer.json:
   ```
   "egulias/email-validator": "1.1.*"
   ```
