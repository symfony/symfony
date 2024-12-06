UPGRADE FROM 7.2 to 7.3
=======================

Symfony 7.3 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.3/setup/upgrade_minor.html).

If you're upgrading from a version below 7.2, follow the [7.2 upgrade guide](UPGRADE-7.2.md) first.

Ldap
----

 * Deprecate `LdapUser::eraseCredentials()`, use `LdapUser::setPassword(null)` instead

Security
--------

 * Deprecate `UserInterface::eraseCredentials()` and `TokenInterface::eraseCredentials()`,
   use a dedicated DTO or erase credentials on your own e.g. upon `AuthenticationTokenCreatedEvent` instead

SecurityBundle
--------------

 * Deprecate the `erase_credentials` config option, erase credentials on your own e.g. upon `AuthenticationTokenCreatedEvent` instead

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

 * Deprecate methods `Command::getDefaultName()` and `Command::getDefaultDescription()` in favor of the `#[AsCommand]` attribute

FrameworkBundle
---------------

 * Not setting the `framework.property_info.with_constructor_extractor` option explicitly is deprecated
   because its default value will change in version 8.0
 * Deprecate the `--show-arguments` option of the `container:debug` command, as arguments are now always shown

Serializer
----------

 * Deprecate the `CompiledClassMetadataFactory` and `CompiledClassMetadataCacheWarmer` classes

Validator
---------

 * Deprecate defining custom constraints not supporting named arguments

   Before:

   ```php
   use Symfony\Component\Validator\Constraint;

   class CustomConstraint extends Constraint
   {
       public function __construct(array $options)
       {
           // ...
       }
   }
   ```

   After:

   ```php
   use Symfony\Component\Validator\Attribute\HasNamedArguments;
   use Symfony\Component\Validator\Constraint;

   class CustomConstraint extends Constraint
   {
       #[HasNamedArguments]
       public function __construct($option1, $option2, $groups, $payload)
       {
           // ...
       }
   }
   ```
 * Deprecate passing an array of options to the constructors of the constraint classes, pass each option as a dedicated argument instead

   Before:

   ```php
   new NotNull([
       'groups' => ['foo', 'bar'],
       'message' => 'a custom constraint violation message',
   ])
   ```

   After:

   ```php
   new NotNull(
       groups: ['foo', 'bar'],
       message: 'a custom constraint violation message',
   )
   ```

TypeInfo
--------

 * Deprecate constructing a `CollectionType` instance as a list that is not an array
 * Deprecate the third `$asList` argument of `TypeFactoryTrait::iterable()`, use `TypeFactoryTrait::list()` instead

VarDumper
---------

 * Deprecate `ResourceCaster::castCurl()`, `ResourceCaster::castGd()` and `ResourceCaster::castOpensslX509()`
 * Mark all casters as `@internal`
 * Deprecate the `CompiledClassMetadataFactory` and `CompiledClassMetadataCacheWarmer` classes
