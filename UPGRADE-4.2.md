UPGRADE FROM 4.1 to 4.2
=======================

Form
----

 * Deprecated the `ChoiceLoaderInterface` implementation in `CountryType`, `LanguageType`, `LocaleType` and `CurrencyType`, use the `choice_loader` option instead.

   Before:
   ```php
   class MyCountryType extends CountryType
   {
       public function loadChoiceList()
       {
           // override the method
       }
   }
   ```

   After:
   ```php
   class MyCountryType extends AbstractType
   {
       public function getParent()
       {
           return CountryType::class;
       }

       public function configureOptions(OptionsResolver $resolver)
       {
           $resolver->setDefault('choice_loader', ...); // override the option instead
       }
   }
   ```
