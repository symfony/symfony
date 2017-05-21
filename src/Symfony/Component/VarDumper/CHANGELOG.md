CHANGELOG
=========

4.0.0
-----

 * support for passing `\ReflectionClass` instances to the `Caster::castObject()`
   method has been dropped, pass class names as strings instead
 * the `Data::getRawData()` method has been removed

2.7.0
-----

 * deprecated Cloner\Data::getLimitedClone(). Use withMaxDepth, withMaxItemsPerDepth or withRefHandles instead.
