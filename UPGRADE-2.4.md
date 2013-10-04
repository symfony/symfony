UPGRADE FROM 2.3 to 2.4
=======================

Form
----

 * The constructor parameter `$precision` in `IntegerToLocalizedStringTransformer`
   is now ignored completely, because a precision does not make sense for
   integers.

Intl
----

 * A new method `getLocaleAliases()` was added to `LocaleBundleInterface`. If
   any of your classes implements this interface, you should add an implementation
   of this method.

 * The methods in the various resource bundles of the `Intl` class used to
   return `null` when invalid arguments were given. These methods throw a
   `NoSuchEntryException` now.

   Before:

   ```
   use Symfony\Component\Intl\Intl;

   // invalid language code
   $language = Intl::getLanguageBundle()->getLanguageName('foo', null, 'en');

   // invalid locale
   $language = Intl::getLanguageBundle()->getLanguageName('de', null, 'foo');

   if (null === $language) {
       // error handling...
   }
   ```

   After:

   ```
   use Symfony\Component\Intl\Intl;
   use Symfony\Component\Intl\Exception\NoSuchEntryException;
   use Symfony\Component\Intl\Exception\NoSuchLocaleException;

   try {
       // invalid language code
       $language = Intl::getLanguageBundle()->getLanguageName('foo', null, 'en');

       // invalid locale
       $language = Intl::getLanguageBundle()->getLanguageName('de', null, 'foo');
   } catch (NoSuchEntryException $e) {
       if ($e->getPrevious() instanceof NoSuchLocaleException) {
           // locale was invalid...
       } else {
           // locale was valid, but entry not found...
       }
   }
   ```

 * The `$fallback` argument of the protected method `AbstractBundle::readEntry()`
   was changed to be `true` by default. This way the signature is consistent
   with the proxied `BundleEntryReaderInterface::readEntry()` method.
   Consequently, if an entry cannot be found for the accessed locale (e.g. "en_GB"),
   it is looked for in the fallback locale (if any, e.g. "en").

   If you extend this class and explicitly want to disable locale fallback, you
   should pass `false` as last argument.

   Before:

   ```
   use Symfony\Component\Intl\ResourceBundle\AbstractBundle;

   class MyBundle extends AbstractBundle
   {
       public function getEntry($key, $locale)
       {
           return $this->readEntry($locale, array('Entries', $key));
       }
   }
   ```

   After:

   ```
   use Symfony\Component\Intl\ResourceBundle\AbstractBundle;

   class MyBundle extends AbstractBundle
   {
       public function getEntry($key, $locale)
       {
           // disable locale fallback!
           return $this->readEntry($locale, array('Entries', $key), false);
       }
   }
   ```

 * The interfaces `CompilationContextInterface` and `StubbingContextInterface`
   were removed. Code against their implementations `CompilationContext` and
   `StubbingContext` in the same namespace instead.

   Before:

   ```
   use Symfony\Component\Intl\ResourceBundle\Transformation\CompilationContextInterface;
   use Symfony\Component\Intl\ResourceBundle\Transformation\StubbingContextInterface;

   public function beforeCompile(CompilationContextInterface $context)
   {
       // ...
   }

   public function beforeCreateStub(StubbingContextInterface $context)
   {
       // ...
   }
   ```

   After:

   ```
   use Symfony\Component\Intl\ResourceBundle\Transformation\CompilationContext;
   use Symfony\Component\Intl\ResourceBundle\Transformation\StubbingContext;

   public function beforeCompile(CompilationContext $context)
   {
       // ...
   }

   public function beforeCreateStub(StubbingContext $context)
   {
       // ...
   }
   ```
