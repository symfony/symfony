CHANGELOG
=========

4.0.0
-----

 * support for passing `\ReflectionClass` instances to the `Caster::castObject()`
   method has been dropped, pass class names as strings instead
 * the `Data::getRawData()` method has been removed
 * the `VarDumperTestTrait::assertDumpEquals()` method expects a 3rd `$context = null`
   argument and moves `$message = ''` argument at 4th position.
 * the `VarDumperTestTrait::assertDumpMatchesFormat()` method expects a 3rd `$context = null`
   argument and moves `$message = ''` argument at 4th position.

3.4.0
-----

 * added `AbstractCloner::setMinDepth()` function to ensure minimum tree depth
 * deprecated `MongoCaster`

2.7.0
-----

 * deprecated Cloner\Data::getLimitedClone(). Use withMaxDepth, withMaxItemsPerDepth or withRefHandles instead.
