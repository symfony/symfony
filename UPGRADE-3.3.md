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
