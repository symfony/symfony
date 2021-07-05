CHANGELOG
=========

5.4
---

 * Add ability to style integer and double values independently
 * Add casters for Symfony's UUIDs and ULIDs

5.2.0
-----

 * added support for PHPUnit `--colors` option
 * added `VAR_DUMPER_FORMAT=server` env var value support
 * prevent replacing the handler when the `VAR_DUMPER_FORMAT` env var is set

5.1.0
-----

 * added `RdKafka` support

4.4.0
-----

 * added `VarDumperTestTrait::setUpVarDumper()` and `VarDumperTestTrait::tearDownVarDumper()`
   to configure casters & flags to use in tests
 * added `ImagineCaster` and infrastructure to dump images
 * added the stamps of a message after it is dispatched in `TraceableMessageBus` and `MessengerDataCollector` collected data
 * added `UuidCaster`
 * made all casters final
 * added support for the `NO_COLOR` env var (https://no-color.org/)

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
