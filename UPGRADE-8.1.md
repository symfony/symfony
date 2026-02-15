UPGRADE FROM 8.0 to 8.1
=======================

Symfony 8.1 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.1/setup/upgrade_minor.html).

If you're upgrading from a version below 8.0, follow the [8.0 upgrade guide](UPGRADE-8.0.md) first.

Console
-------

 * [BC BREAK] Add `object` support to input options and arguments' default by changing the `$default` type to `mixed` in `InputArgument`, `InputOption`, `#[Argument]` and `#[Option]`

DependencyInjection
-------------------

 * Deprecate configuring options `alias`, `parent`, `synthetic`, `file`, `arguments`, `properties`, `configurator` or `calls` when using `from_callable`
 * Deprecate default index/priority methods when defining tagged locators/iterators; use the `#[AsTaggedItem]` attribute instead

DoctrineBridge
--------------

 * Deprecate setting an `$aliasMap` in `RegisterMappingsPass`. Namespace aliases are no longer supported in Doctrine.

Form
----

 * Deprecate passing boolean as the second argument of `ValidatorExtension` and `FormTypeValidatorExtension`'s constructors; pass a `ViolationMapperInterface` instead
 * Add argument `$violationMapper` to `ValidatorExtensionTrait` and `TypeTestCase`'s `getExtensions()` methods

Filesystem
----------

 * Deprecate calling `Filesystem::mirror()` with option `copy_on_windows`, use option `follow_symlinks` instead

FrameworkBundle
---------------

 * Deprecate setting the `framework.profiler.collect_serializer_data` config option
 * Deprecate parameters `router.request_context.scheme` and `router.request_context.host`;
   use the `router.request_context.base_url` parameter or the `framework.router.default_uri` config option instead

HttpKernel
----------

 * Deprecate passing a non-flat list of attributes to `Controller::setController()`
 * Deprecate the `Symfony\Component\HttpKernel\DependencyInjection\Extension` class, use the parent `Symfony\Component\DependencyInjection\Extension\Extension` class instead:

   ```diff
   - use Symfony\Component\HttpKernel\DependencyInjection\Extension;
   + use Symfony\Component\DependencyInjection\Extension\Extension;

   class ExampleExtension extends Extension
   {
       // ...
   }
   ```
 * Deprecate passing a `ControllerArgumentsEvent` to the `ViewEvent` constructor; pass a `ControllerArgumentsMetadata` instead

Security
--------

 * Add `getParentRoleNames()` method to `RoleHierarchyInterface`
 * Make `RoleHierarchyInterface::getReachableRoleNames()` return roles as both keys and values
 * Deprecate `SameOriginCsrfTokenManager::onKernelResponse()`, `SameOriginCsrfTokenManager::clearCookies()` and `SameOriginCsrfTokenManager::persistStrategy()`; this logic is now handled automatically by `SameOriginCsrfListener`

Serializer
----------

 * Deprecate datetime constructor as a fallback, in version 9.0 a `Symfony\Component\Serializer\Exception\NotNormalizableValueException` will be thrown when a date could not be parsed using the default format

Uid
---

 * Add argument `$format` to `Ulid::isValid()`
