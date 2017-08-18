UPGRADE FROM 3.0 to 3.1
=======================

DependencyInjection
-------------------

 * Using unsupported configuration keys in YAML configuration files has been
   deprecated and will raise an exception in Symfony 4.0.

 * Using unsupported options to configure service aliases has been deprecated
   and will raise an exception in Symfony 4.0.

Form
----

 * The `choices_as_values` option of the `ChoiceType` has been deprecated and
   will be removed in Symfony 4.0.

 * Support for data objects that implements both `Traversable` and `ArrayAccess`
   in `ResizeFormListener::preSubmit` method has been deprecated and will be
   removed in Symfony 4.0.
 * `TextType` now implements `DataTransformerInterface` and will always return
   an empty string when `empty_data` option is explicitly assigned to it.

 * Using callable strings as choice options in ChoiceType has been deprecated
   in favor of `PropertyPath` in Symfony 4.0 use a "\Closure" instead.

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

 * Caching of the loaded `ChoiceListInterface` in the `LazyChoiceList` has been deprecated,
   it must be cached in the `ChoiceLoaderInterface` implementation instead.

FrameworkBundle
---------------

 * As it was never an officially supported feature, the support for absolute
   template paths has been deprecated and will be removed in Symfony 4.0.

 * The abstract `Controller` class now has a `json()` helper method that creates
   a `JsonResponse`. If you have existing controllers extending `Controller`
   that contain a method with this name, you need to rename that method to avoid
   conflicts.

 * The following form types registered as services have been deprecated and
   will be removed in Symfony 4.0; use their fully-qualified class name instead:

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

 * The `framework.serializer.cache` option and the service
   `serializer.mapping.cache.apc` have been deprecated. APCu should now be
   automatically used when available.

HttpKernel
----------

 * Passing non-scalar values as URI attributes to the ESI and SSI renderers has been
   deprecated and will be removed in Symfony 4.0. The inline fragment
   renderer should be used with non-scalar attributes.

 * The `ControllerResolver::getArguments()` method has been deprecated and will
   be removed in 4.0. If you have your own `ControllerResolverInterface`
   implementation, you should inject either an `ArgumentResolverInterface`
   instance or the new `ArgumentResolver` in the `HttpKernel`.

Serializer
----------

 * Passing a Doctrine `Cache` instance to the `ClassMetadataFactory` has been
   deprecated and will not be supported in Symfony 4.0. You should use the
   `CacheClassMetadataFactory` class instead.

 * The `AbstractObjectNormalizer::isAttributeToNormalize()` method has been removed
   because it was initially added by mistake, has never been used and is not tested
   nor documented.

Translation
-----------

 * Deprecated the backup feature of the file dumper classes. It will be removed
   in Symfony 4.0.

Yaml
----

 * Usage of `%` at the beginning of an unquoted string has been deprecated and
   will lead to a `ParseException` in Symfony 4.0.

 * The `Dumper::setIndentation()` method is deprecated and will be removed in
   Symfony 4.0. Pass the indentation level to the constructor instead.

 * Deprecated support for passing `true`/`false` as the second argument to the
   `parse()` method to trigger exceptions when an invalid type was passed.

   Before:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', true);
   ```

   After:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
   ```

 * Deprecated support for passing `true`/`false` as the third argument to the
   `parse()` method to toggle object support.

   Before:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', false, true);
   ```

   After:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', Yaml::PARSE_OBJECT);
   ```

 * Deprecated support for passing `true`/`false` as the fourth argument to the
   `parse()` method to parse objects as maps.

   Before:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', false, false, true);
   ```

   After:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', Yaml::PARSE_OBJECT_FOR_MAP);
   ```

 * Deprecated support for passing `true`/`false` as the fourth argument to the
   `dump()` method to trigger exceptions when an invalid type was passed.

   Before:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, true);
   ```

   After:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE);
   ```

 * Deprecated support for passing `true`/`false` as the fifth argument to the
   `dump()` method to toggle object support.

   Before:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, true);
   ```

   After:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, Yaml::DUMP_OBJECT);
   ```

 * The `!!php/object` tag to indicate dumped PHP objects has been deprecated
   and will be removed in Symfony 4.0. Use the `!php/object` tag instead.

Validator
---------

 * The `DateTimeValidator::PATTERN` constant has been deprecated and will be
   removed in Symfony 4.0.
