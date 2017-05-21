CHANGELOG
=========

3.2.0
-----

 * Mappings with a colon (`:`) that is not followed by a whitespace are deprecated
   when the mapping key is not quoted and will lead to a `ParseException` in
   Symfony 4.0 (e.g. `foo:bar` must be `foo: bar`).

 * Added support for parsing PHP constants:

   ```php
   Yaml::parse('!php/const:PHP_INT_MAX', Yaml::PARSE_CONSTANT);
   ```

 * Support for silently ignoring duplicate mapping keys in YAML has been
   deprecated and will lead to a `ParseException` in Symfony 4.0.

3.1.0
-----

 * Added support to dump `stdClass` and `ArrayAccess` objects as YAML mappings
   through the `Yaml::DUMP_OBJECT_AS_MAP` flag.

 * Strings that are not UTF-8 encoded will be dumped as base64 encoded binary
   data.

 * Added support for dumping multi line strings as literal blocks.

 * Added support for parsing base64 encoded binary data when they are tagged
   with the `!!binary` tag.

 * Added support for parsing timestamps as `\DateTime` objects:

   ```php
   Yaml::parse('2001-12-15 21:59:43.10 -5', Yaml::PARSE_DATETIME);
   ```

 * `\DateTime` and `\DateTimeImmutable` objects are dumped as YAML timestamps.

 * Deprecated usage of `%` at the beginning of an unquoted string.

 * Added support for customizing the YAML parser behavior through an optional bit field:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT | Yaml::PARSE_OBJECT_FOR_MAP);
   ```

 * Added support for customizing the dumped YAML string through an optional bit field:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE | Yaml::DUMP_OBJECT);
   ```

3.0.0
-----

 * Yaml::parse() now throws an exception when a blackslash is not escaped
   in double-quoted strings

2.8.0
-----

 * Deprecated usage of a colon in an unquoted mapping value
 * Deprecated usage of @, \`, | and > at the beginning of an unquoted string
 * When surrounding strings with double-quotes, you must now escape `\` characters. Not
   escaping those characters (when surrounded by double-quotes) is deprecated.

   Before:

   ```yml
   class: "Foo\Var"
   ```

   After:

   ```yml
   class: "Foo\\Var"
   ```

2.1.0
-----

 * Yaml::parse() does not evaluate loaded files as PHP files by default
   anymore (call Yaml::enablePhpParsing() to get back the old behavior)
