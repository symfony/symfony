CHANGELOG
=========

7.3
---

 * Add support for `non-positive-int`, `non-negative-int` and `non-zero-int` PHPStan types to `PhpStanExtractor`
 * Add `PropertyDescriptionExtractorInterface` to `PhpStanExtractor`
 * Deprecate the `Type` class, use `Symfony\Component\TypeInfo\Type` class from `symfony/type-info` instead
 * Deprecate the `PropertyTypeExtractorInterface::getTypes()` method, use `PropertyTypeExtractorInterface::getType()` instead
 * Deprecate the `ConstructorArgumentTypeExtractorInterface::getTypesFromConstructor()` method, use `ConstructorArgumentTypeExtractorInterface::getTypeFromConstructor()` instead

7.1
---

 * Introduce `PropertyDocBlockExtractorInterface` to extract a property's doc block
 * Restrict access to `PhpStanExtractor` based on visibility
 * Add `PropertyTypeExtractorInterface::getType()` as experimental

6.4
---

 * Make properties writable when a setter in camelCase exists, similar to the camelCase getter

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
