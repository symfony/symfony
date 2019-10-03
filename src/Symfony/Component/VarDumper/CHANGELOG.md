CHANGELOG
=========

4.3.0
-----

 * added `DsCaster` to support dumping the contents of data structures from the Ds extension

4.2.0
-----

 * support selecting the format to use by setting the environment variable `VAR_DUMPER_FORMAT` to `html` or `cli`

4.1.0
-----

 * added a `ServerDumper` to send serialized Data clones to a server
 * added a `ServerDumpCommand` and `DumpServer` to run a server collecting
   and displaying dumps on a single place with multiple formats support
 * added `CliDescriptor` and `HtmlDescriptor` descriptors for `server:dump` CLI and HTML formats support

4.0.0
-----

 * support for passing `\ReflectionClass` instances to the `Caster::castObject()`
   method has been dropped, pass class names as strings instead
 * the `Data::getRawData()` method has been removed
 * the `VarDumperTestTrait::assertDumpEquals()` method expects a 3rd `$filter = 0`
   argument and moves `$message = ''` argument at 4th position.
 * the `VarDumperTestTrait::assertDumpMatchesFormat()` method expects a 3rd `$filter = 0`
   argument and moves `$message = ''` argument at 4th position.

3.4.0
-----

 * added `AbstractCloner::setMinDepth()` function to ensure minimum tree depth
 * deprecated `MongoCaster`

2.7.0
-----

 * deprecated `Cloner\Data::getLimitedClone()`. Use `withMaxDepth`, `withMaxItemsPerDepth` or `withRefHandles` instead.
