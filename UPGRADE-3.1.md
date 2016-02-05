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

HttpKernel
----------

 * Passing objects as URI attributes to the ESI and SSI renderers has been
   deprecated and will be removed in Symfony 4.0. The inline fragment
   renderer should be used with object attributes.

Serializer
----------

 * Passing a Doctrine `Cache` instance to the `ClassMetadataFactory` has been
   deprecated and will not be supported in Symfony 4.0. You should use the
   `CacheClassMetadataFactory` class instead.

Yaml
----

 * Deprecated support for passing `true`/`false` as the third argument to the `dump()` methods to toggle object support.

   Before:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, true);
   ```

   After:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, Yaml::DUMP_OBJECT);

 * The `!!php/object` tag to indicate dumped PHP objects has been deprecated
   and will be removed in Symfony 4.0. Use the `!php/object` tag instead.
