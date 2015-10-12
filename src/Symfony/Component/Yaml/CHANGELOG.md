CHANGELOG
=========

2.8.0
-----

 * Deprecated non-escaped \ in double-quoted strings when parsing Yaml
   ("Foo\Var" is not valid whereas "Foo\\Var" is)

2.1.0
-----

 * Yaml::parse() does not evaluate loaded files as PHP files by default
   anymore (call Yaml::enablePhpParsing() to get back the old behavior)
