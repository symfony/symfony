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

FrameworkBundle
---------------

 * As it was never an officially supported feature, the support for absolute
   template paths has been deprecated and will be removed in Symfony 4.0.

HttpKernel
----------

 * Passing objects as URI attributes to the ESI and SSI renderers has been
   deprecated and will be removed in Symfony 4.0. The inline fragment
   renderer should be used with object attributes.
 * The `ControllerResolver::getArguments()` method is deprecated and will be
   removed in 4.0. If you have your own `ControllerResolverInterface`
   implementation, you should replace this method by implementing the
   `ArgumentResolverInterface` and injecting it in the HttpKernel.

Serializer
----------

 * Passing a Doctrine `Cache` instance to the `ClassMetadataFactory` has been
   deprecated and will not be supported in Symfony 4.0. You should use the
   `CacheClassMetadataFactory` class instead.

Yaml
----

 * Deprecated usage of `%` at the beginning of an unquoted string.

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

 * Deprecated support for passing `true`/`false` as the fifth argument to the `dump()` method to toggle object support.

   Before:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, true);
   ```

   After:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, Yaml::DUMP_OBJECT);
   ```

 * The `!!php/object` tag to indicate dumped PHP objects has been deprecated
   and will be removed in Symfony 4.0. Use the `!php/object` tag instead.

Validator
---------

 * The `DateTimeValidator::PATTERN` constant is deprecated and will be removed in
   Symfony 4.0.
