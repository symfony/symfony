UPGRADE FROM 7.2 to 7.3
=======================

Symfony 7.3 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.3/setup/upgrade_minor.html).

If you're upgrading from a version below 7.1, follow the [7.2 upgrade guide](UPGRADE-7.2.md) first.

Console
-------

 * Omitting parameter types in callables configured via `Command::setCode` method is deprecated

   *Before*
   ```php
   $command->setCode(function ($input, $output) {
       // ...
   });
   ```

   *After*
   ```php
   use Symfony\Component\Console\Input\InputInterface;
   use Symfony\Component\Console\Output\OutputInterface;

   $command->setCode(function (InputInterface $input, OutputInterface $output) {
       // ...
   });
   ```

FrameworkBundle
---------------

 * Not setting the `framework.property_info.with_constructor_extractor` option explicitly is deprecated
   because its default value will change in version 8.0
 * Deprecate the `--show-arguments` option of the `container:debug` command, as arguments are now always shown

Serializer
----------

 * Deprecate the `CompiledClassMetadataFactory` and `CompiledClassMetadataCacheWarmer` classes

VarDumper
---------

 * Deprecate `ResourceCaster::castCurl()`, `ResourceCaster::castGd()` and `ResourceCaster::castOpensslX509()`
 * Mark all casters as `@internal`
