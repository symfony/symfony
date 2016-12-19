UPGRADE FROM 3.2 to 3.3
=======================

ClassLoader
-----------

 * The ApcClassLoader, WinCacheClassLoader and XcacheClassLoader classes have been deprecated
   in favor of the `--apcu-autoloader` option introduced in composer 1.3

DependencyInjection
-------------------

 * Using the `PhpDumper` with an uncompiled `ContainerBuilder` is deprecated and
   will not be supported anymore in 4.0.

 * The `DefinitionDecorator` class is deprecated and will be removed in 4.0, use
   the `ChildDefinition` class instead.

Finder
------

 * The `ExceptionInterface` has been deprecated and will be removed in 4.0.

HttpKernel
-----------

 * The `Psr6CacheClearer::addPool()` method has been deprecated. Pass an array of pools indexed
   by name to the constructor instead.

Security
--------

 * The `RoleInterface` has been deprecated. Extend the `Symfony\Component\Security\Core\Role\Role`
   class in your custom role implementations instead.

SecurityBundle
--------------

 * The `FirewallContext::getContext()` method has been deprecated and will be removed in 4.0.
   Use the `getListeners()` method instead.
