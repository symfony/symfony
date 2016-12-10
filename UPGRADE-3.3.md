UPGRADE FROM 3.2 to 3.3
=======================

ClassLoader
-----------

 * The ApcClassLoader, WinCacheClassLoader and XcacheClassLoader classes have been deprecated
   in favor of the `--apcu-autoloader` option introduced in composer 1.3

Security
--------

 * The `RoleInterface` has been deprecated. Extend the `Symfony\Component\Security\Core\Role\Role`
   class in your custom role implementations instead.

SecurityBundle
--------------

 * The `FirewallContext::getContext()` method has been deprecated and will be removed in 4.0.
   Use the `getListeners()` method instead.
   
HttpKernel
-----------

 * The `Psr6CacheClearer::addPool()` method has been deprecated. Pass an array of pools indexed
   by name to the constructor instead.
