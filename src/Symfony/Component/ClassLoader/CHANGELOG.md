CHANGELOG
=========

2.4.0
-----

 * deprecated the UniversalClassLoader in favor of the ClassLoader class instead
 * deprecated the ApcUniversalClassLoader in favor of the ApcClassLoader class instead
 * deprecated the DebugUniversalClassLoader in favor of the DebugClassLoader class from the Debug component
 * deprecated the DebugClassLoader as it has been moved to the Debug component instead

2.3.0
-----

 * added a WinCacheClassLoader for WinCache

2.1.0
-----

 * added a DebugClassLoader able to wrap any autoloader providing a findFile
   method
 * added a new ApcClassLoader and XcacheClassLoader using composition to wrap
   other loaders
 * added a new ClassLoader which does not distinguish between namespaced and
   pear-like classes (as the PEAR convention is a subset of PSR-0) and
   supports using Composer's namespace maps
 * added a class map generator
 * added support for loading globally-installed PEAR packages
