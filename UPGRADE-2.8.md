UPGRADE FROM 2.7 to 2.8
=======================

Translator
----------
 * The `getMessages()` method of the `Symfony\Component\Translation\Translator` was deprecated and will be removed in
   Symfony 3.0. You should use the `getCatalogue()` method of the `Symfony\Component\Translation\TranslatorBagInterface`.

   Before:

   ```php
   $messages = $translator->getMessages();
   ```

   After:

   ```php
    $catalogue = $translator->getCatalogue($locale);
    $messages = $catalogue->all();

    while ($catalogue = $catalogue->getFallbackCatalogue()) {
        $messages = array_replace_recursive($catalogue->all(), $messages);
    }
   ```

 * The visibility of the `locale` property has been changed from protected to private. Rely on `getLocale` and `setLocale`
   instead.

   Before:

   ```php
    class CustomTranslator extends Translator
    {
        public function fooMethod()
        {
           // get locale
           $locale = $this->locale;

           // update locale
           $this->locale = $locale;
        }
    }
   ```

   After:

   ```php
    class CustomTranslator extends Translator
    {
        public function fooMethod()
        {
           // get locale
           $locale = $this->getLocale();

           // update locale
           $this->setLocale($locale);
       }
    }
   ```
