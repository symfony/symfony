UPGRADE FROM 3.x to 4.0
=======================

Console
-------

 * Setting unknown style options is not supported anymore and throws an
   exception.

 * The `QuestionHelper::setInputStream()` method is removed. Use
   `StreamableInputInterface::setStream()` or `CommandTester::setInputs()`
   instead.

   Before:

   ```php
   $input = new ArrayInput();

   $questionHelper->setInputStream($stream);
   $questionHelper->ask($input, $output, $question);
   ```

   After:

   ```php
   $input = new ArrayInput();
   $input->setStream($stream);

   $questionHelper->ask($input, $output, $question);
   ```

   Before:

   ```php
   $commandTester = new CommandTester($command);

   $stream = fopen('php://memory', 'r+', false);
   fputs($stream, "AppBundle\nYes");
   rewind($stream);

   $command->getHelper('question')->setInputStream($stream);

   $commandTester->execute();
   ```

   After:

   ```php
   $commandTester = new CommandTester($command);

   $commandTester->setInputs(array('AppBundle', 'Yes'));

   $commandTester->execute();
   ```

Debug
-----

 * `FlattenException::getTrace()` now returns additional type descriptions
   `integer` and `float`.

DependencyInjection
-------------------

 * Using unsupported configuration keys in YAML configuration files raises an
   exception.

 * Using unsupported options to configure service aliases raises an exception.

 * Setting or unsetting a private service with the `Container::set()` method is
   no longer supported. Only public services can be set or unset.

 * Checking the existence of a private service with the `Container::has()`
   method is no longer supported and will return `false`.

 * Requesting a private service with the `Container::get()` method is no longer
   supported.

ExpressionLanguage
----------

 * The ability to pass a `ParserCacheInterface` instance to the `ExpressionLanguage`
   class has been removed. You should use the `CacheItemPoolInterface` interface
   instead.

Form
----

 * The `choices_as_values` option of the `ChoiceType` has been removed.

 * Support for data objects that implements both `Traversable` and
   `ArrayAccess` in `ResizeFormListener::preSubmit` method has been removed.

 * Using callable strings as choice options in ChoiceType is not supported
   anymore in favor of passing PropertyPath instances.

   Before:

   ```php
   'choice_value' => new PropertyPath('range'),
   'choice_label' => 'strtoupper',
   ```

   After:

   ```php
   'choice_value' => 'range',
   'choice_label' => function ($choice) {
       return strtoupper($choice);
   },
   ```

 * Caching of the loaded `ChoiceListInterface` in the `LazyChoiceList` has been removed,
   it must be cached in the `ChoiceLoaderInterface` implementation instead.

 * Calling `isValid()` on a `Form` instance before submitting it is not supported
   anymore and raises an exception.

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

 * Support for absolute template paths has been removed.

 * The following form types registered as services have been removed; use their
   fully-qualified class name instead:

    - `"form.type.birthday"`
    - `"form.type.checkbox"`
    - `"form.type.collection"`
    - `"form.type.country"`
    - `"form.type.currency"`
    - `"form.type.date"`
    - `"form.type.datetime"`
    - `"form.type.email"`
    - `"form.type.file"`
    - `"form.type.hidden"`
    - `"form.type.integer"`
    - `"form.type.language"`
    - `"form.type.locale"`
    - `"form.type.money"`
    - `"form.type.number"`
    - `"form.type.password"`
    - `"form.type.percent"`
    - `"form.type.radio"`
    - `"form.type.range"`
    - `"form.type.repeated"`
    - `"form.type.search"`
    - `"form.type.textarea"`
    - `"form.type.text"`
    - `"form.type.time"`
    - `"form.type.timezone"`
    - `"form.type.url"`
    - `"form.type.button"`
    - `"form.type.submit"`
    - `"form.type.reset"`

 * The `framework.serializer.cache` option and the services
   `serializer.mapping.cache.apc` and `serializer.mapping.cache.doctrine.apc`
   have been removed. APCu should now be automatically used when available.

HttpFoundation
---------------

 * Extending the following methods of `Response`
   is no longer possible (these methods are now `final`):

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

 * The ability to check only for cacheable HTTP methods using `Request::isMethodSafe()` is
   not supported anymore, use `Request::isMethodCacheable()` instead.

HttpKernel
----------

 * Possibility to pass non-scalar values as URI attributes to the ESI and SSI
   renderers has been removed. The inline fragment renderer should be used with
   non-scalar attributes.

 * The `ControllerResolver::getArguments()` method has been removed. If you
   have your own `ControllerResolverInterface` implementation, you should
   inject an `ArgumentResolverInterface` instance.

 * The `DataCollector::varToString()` method has been removed in favor of `cloneVar()`.

Serializer
----------

 * The ability to pass a Doctrine `Cache` instance to the `ClassMetadataFactory`
   class has been removed. You should use the `CacheClassMetadataFactory` class
   instead.
   
 * Not defining the 6th argument `$format = null` of the
   `AbstractNormalizer::instantiateObject()` method when overriding it is not
   supported anymore.

Translation
-----------

 * Removed the backup feature from the file dumper classes.

TwigBridge
----------

 * Removed the possibility to inject the Form `TwigRenderer` into the `FormExtension`.
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

 * Removed the `TwigRendererEngineInterface` interface.

Validator
---------

 * The `DateTimeValidator::PATTERN` constant was removed.

 * `Tests\Constraints\AbstractConstraintValidatorTest` has been removed in
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
   
 * The default value of the strict option of the `Choice` Constraint has been
   changed to `true` as of 4.0. If you need the previous behaviour ensure to 
   set the option to `false`.

Yaml
----

 * Mappings with a colon (`:`) that is not followed by a whitespace are not
   supported anymore and lead to a `ParseException`(e.g. `foo:bar` must be
   `foo: bar`).

 * Starting an unquoted string with `%` leads to a `ParseException`.

 * The `Dumper::setIndentation()` method was removed. Pass the indentation
   level to the constructor instead.

 * Removed support for passing `true`/`false` as the second argument to the
   `parse()` method to trigger exceptions when an invalid type was passed.

   Before:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', true);
   ```

   After:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
   ```

 * Removed support for passing `true`/`false` as the third argument to the
   `parse()` method to toggle object support.

   Before:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', false, true);
   ```

   After:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', Yaml::PARSE_OBJECT);
   ```

 * Removed support for passing `true`/`false` as the fourth argument to the
   `parse()` method to parse objects as maps.

   Before:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', false, false, true);
   ```

   After:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', Yaml::PARSE_OBJECT_FOR_MAP);
   ```

 * Removed support for passing `true`/`false` as the fourth argument to the
   `dump()` method to trigger exceptions when an invalid type was passed.

   Before:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, true);
   ```

   After:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE);
   ```

 * Removed support for passing `true`/`false` as the fifth argument to the
   `dump()` method to toggle object support.

   Before:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, true);
   ```

   After:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, Yaml::DUMP_OBJECT);
   ```

 * The `!!php/object` tag to indicate dumped PHP objects was removed in favor of
   the `!php/object` tag.

 * Duplicate mapping keys lead to a `ParseException`.
