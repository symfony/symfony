UPGRADE FROM 3.1 to 3.2
=======================

BrowserKit
----------

 * Client HTTP user agent has been changed to 'Symfony BrowserKit' (was 'Symfony2 BrowserKit' before).

Console
-------

 * Setting unknown style options is deprecated and will throw an exception in
   Symfony 4.0.

DependencyInjection
-------------------

 * Calling `get()` on a `ContainerBuilder` instance before compiling the
   container is deprecated and will throw an exception in Symfony 4.0.

ExpressionLanguage
-------------------

* Passing a `ParserCacheInterface` instance to the `ExpressionLanguage` has been
  deprecated and will not be supported in Symfony 4.0. You should use the
  `CacheItemPoolInterface` interface instead.

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

 * The `doctrine/annotations` dependency has been removed; require it via `composer
   require doctrine/annotations` if you are using annotations in your project
 * The `symfony/security-core` and `symfony/security-csrf` dependencies have
   been removed; require them via `composer require symfony/security-core
   symfony/security-csrf` if you depend on them and don't already depend on
   `symfony/symfony`
 * The `symfony/templating` dependency has been removed; require it via `composer
   require symfony/templating` if you depend on it and don't already depend on
   `symfony/symfony`
 * The `symfony/translation` dependency has been removed; require it via `composer
   require symfony/translation` if you depend on it and don't already depend on
   `symfony/symfony`
 * The `symfony/asset` dependency has been removed; require it via `composer
   require symfony/asset` if you depend on it and don't already depend on
   `symfony/symfony`
 * The `Resources/public/images/*` files have been removed.
 * The `Resources/public/css/*.css` files have been removed (they are now inlined
   in TwigBundle).
 * The service `serializer.mapping.cache.doctrine.apc` is deprecated. APCu should now
   be automatically used when available.

HttpFoundation
---------------

  * Extending the following methods of `Response`
    is deprecated (these methods will be `final` in 4.0):

     - `setDate`/`getDate`
     - `setExpires`/`getExpires`
     - `setLastModified`/`getLastModified`
     - `setProtocolVersion`/`getProtocolVersion`
     - `setStatusCode`/`getStatusCode`
     - `setCharset`/`getCharset`
     - `setPrivate`/`setPublic`
     - `getAge`
     - `getMaxAge`/`setMaxAge`
     - `setSharedMaxAge`
     - `getTtl`/`setTtl`
     - `setClientTtl`
     - `getEtag`/`setEtag`
     - `hasVary`/`getVary`/`setVary`
     - `isInvalid`/`isSuccessful`/`isRedirection`/`isClientError`/`isServerError`
     - `isOk`/`isForbidden`/`isNotFound`/`isRedirect`/`isEmpty`

  * Checking only for cacheable HTTP methods with `Request::isMethodSafe()` is deprecated 
    since version 3.2 and will throw an exception in 4.0. Disable checking only for 
    cacheable methods by calling the method with `false` as first argument or use 
    `Request::isMethodCacheable()` instead.

HttpKernel
----------

 * `DataCollector::varToString()` is deprecated and will be removed in Symfony
   4.0. Use the `cloneVar()` method instead.

 * Surrogate name in a `Surrogate-Capability` HTTP request header has been changed to 'symfony'.

   Before:
   ```
   Surrogate-Capability: symfony2="ESI/1.0"
   ```

   After:
   ```
   Surrogate-Capability: symfony="ESI/1.0"
   ```

Router
------

 * `UrlGenerator` now generates URLs in compliance with [`RFC 3986`](https://www.ietf.org/rfc/rfc3986.txt),
    which means spaces will be percent encoded (%20) inside query strings.

Serializer
----------

 * Method `AbstractNormalizer::instantiateObject()` will have a 6th
   `$format = null` argument in Symfony 4.0. Not defining it when overriding
   the method is deprecated.

TwigBridge
----------

 * Injecting the Form `TwigRenderer` into the `FormExtension` is deprecated and has no more effect.
   Upgrade Twig to `^1.30`, inject the `Twig_Environment` into the `TwigRendererEngine` and load
   the `TwigRenderer` using the `Twig_FactoryRuntimeLoader` instead.

   Before:

   ```php
   use Symfony\Bridge\Twig\Extension\FormExtension;
   use Symfony\Bridge\Twig\Form\TwigRenderer;
   use Symfony\Bridge\Twig\Form\TwigRendererEngine;

   // ...
   $rendererEngine = new TwigRendererEngine(array('form_div_layout.html.twig'));
   $rendererEngine->setEnvironment($twig);
   $twig->addExtension(new FormExtension(new TwigRenderer($rendererEngine, $csrfTokenManager)));
   ```

   After:

   ```php
   $rendererEngine = new TwigRendererEngine(array('form_div_layout.html.twig'), $twig);
   $twig->addRuntimeLoader(new \Twig_FactoryRuntimeLoader(array(
       TwigRenderer::class => function () use ($rendererEngine, $csrfTokenManager) {
           return new TwigRenderer($rendererEngine, $csrfTokenManager);
       },
   )));
   $twig->addExtension(new FormExtension());
   ```

 * Deprecated the `TwigRendererEngineInterface` interface, it will be removed in 4.0.

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

Yaml
----

 * Support for silently ignoring duplicate mapping keys in YAML has been
   deprecated and will lead to a `ParseException` in Symfony 4.0.

 * Mappings with a colon (`:`) that is not followed by a whitespace are deprecated
   and will lead to a `ParseException` in Symfony 4.0 (e.g. `foo:bar` must be
   `foo: bar`).
