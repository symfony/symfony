CHANGELOG
=========

6.1
---

 * Add support for phpDocumentor and PHPStan pseudo-types
 * Add PHP 8.0 promoted properties `@param` mutation support to `PhpDocExtractor`
 * Add PHP 8.0 promoted properties `@param` mutation support to `PhpStanExtractor`

6.0
---

 * Remove the `Type::getCollectionKeyType()` and `Type::getCollectionValueType()` methods, use `Type::getCollectionKeyTypes()` and `Type::getCollectionValueTypes()` instead
 * Remove the `enable_magic_call_extraction` context option in `ReflectionExtractor::getWriteInfo()` and `ReflectionExtractor::getReadInfo()` in favor of `enable_magic_methods_extraction`

5.4
---

 * Add PhpStanExtractor

5.3
---

 * Add support for multiple types for collection keys & values
 * Deprecate the `Type::getCollectionKeyType()` and `Type::getCollectionValueType()` methods, use `Type::getCollectionKeyTypes()` and `Type::getCollectionValueTypes()` instead

5.2.0
-----

 * deprecated the `enable_magic_call_extraction` context option in `ReflectionExtractor::getWriteInfo()` and `ReflectionExtractor::getReadInfo()` in favor of `enable_magic_methods_extraction`

5.1.0
-----

 * Add support for extracting accessor and mutator via PHP Reflection

4.3.0
-----

 * Added the ability to extract private and protected properties and methods on `ReflectionExtractor`
 * Added the ability to extract property type based on its initial value

4.2.0
-----

 * added `PropertyInitializableExtractorInterface` to test if a property can be initialized through the constructor (implemented by `ReflectionExtractor`)

3.3.0
-----

 * Added `PropertyInfoPass`
