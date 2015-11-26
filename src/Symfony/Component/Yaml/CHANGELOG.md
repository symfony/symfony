CHANGELOG
=========

3.0.0
-----

 * Yaml::parse() now throws an exception when a blackslash is not escaped
   in double-quoted strings

2.8.0
-----

 * Deprecated usage of a colon in an unquoted mapping value
 * Deprecated usage of @, \`, | and > at the beginning of an unquoted string
 * Deprecated non-escaped \ in double-quoted strings when parsing Yaml
   ("Foo\Var" is not valid whereas "Foo\\Var" is)

2.1.0
-----

 * Yaml::parse() does not evaluate loaded files as PHP files by default
   anymore (call Yaml::enablePhpParsing() to get back the old behavior)
