CHANGELOG
=========

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
