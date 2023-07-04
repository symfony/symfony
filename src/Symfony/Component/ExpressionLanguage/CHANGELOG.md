CHANGELOG
=========

7.0
---

 * The `in` and `not in` operators now use strict comparison

6.3
---

 * Add `enum` expression function
 * Deprecate loose comparisons when using the "in" operator; normalize the array parameter
   so it only has the expected types or implement loose matching in your own expression function

6.2
---

 * Add support for null-coalescing syntax

6.1
---

 * Add support for null-safe syntax when parsing object's methods and properties
 * Add new operators: `contains`, `starts with` and `ends with`
 * Support lexing numbers with the numeric literal separator `_`
 * Support lexing decimals with no leading zero

5.1.0
-----

 * added `lint` method to `ExpressionLanguage` class
 * added `lint` method to `Parser` class

4.0.0
-----

 * the first argument of the `ExpressionLanguage` constructor must be an instance
   of `CacheItemPoolInterface`
 * removed the `ArrayParserCache` and `ParserCacheAdapter` classes
 * removed the `ParserCacheInterface`

2.6.0
-----

 * Added ExpressionFunction and ExpressionFunctionProviderInterface

2.4.0
-----

 * added the component
