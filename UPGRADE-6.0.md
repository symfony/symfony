UPGRADE FROM 5.x to 6.0
=======================

HttpKernel
----------

 * Removed `Bundle\BundleInterface`,
   use `Symfony\Component\Kernel\Bundle\BundleInterface` instead
 * Removed `Bundle\Bundle`,
   use `Symfony\Component\Kernel\Bundle\Bundle` instead
 * Removed `CacheClearer\CacheClearerInterface`,
   use `Symfony\Component\Kernel\CacheClearer\CacheClearerInterface` instead
 * Removed `CacheClearer\ChainCacheClearer`,
   use `Symfony\Component\Kernel\CacheClearer\ChainCacheClearer` instead
 * Removed `CacheClearer\Psr6CacheClearer`,
   use `Symfony\Component\Kernel\CacheClearer\Psr6CacheClearer` instead
 * Removed `CacheWarmer\CacheWarmerAggregate`,
   use `Symfony\Component\Kernel\CacheWarmer\CacheWarmerAggregate` instead
 * Removed `CacheWarmer\CacheWarmerInterface`,
   use `Symfony\Component\Kernel\CacheWarmer\CacheWarmerInterface` instead
 * Removed `CacheWarmer\CacheWarmer`,
   use `Symfony\Component\Kernel\CacheWarmer\CacheWarmer` instead
 * Removed `CacheWarmer\WarmableInterface`,
   use `Symfony\Component\Kernel\CacheWarmer\WarmableInterface` instead
 * Removed `Config\FileLocator`,
   use `Symfony\Component\Kernel\Config\FileLocator` instead
 * Removed `DependencyInjection\AddAnnotatedClassesToCachePass`,
   use `Symfony\Component\Kernel\DependencyInjection\AddAnnotatedClassesToCachePass` instead
 * Removed `DependencyInjection\ConfigurableExtension`,
   use `Symfony\Component\Kernel\DependencyInjection\ConfigurableExtension` instead
 * Removed `DependencyInjection\Extension`,
   use `Symfony\Component\Kernel\DependencyInjection\Extension` instead
 * Removed `DependencyInjection\LoggerPass`,
   use `Symfony\Component\Kernel\DependencyInjection\LoggerPass` instead
 * Removed `DependencyInjection\MergeExtensionConfigurationPass`,
   use `Symfony\Component\Kernel\DependencyInjection\MergeExtensionConfigurationPass` instead
 * Removed `DependencyInjection\ResettableServicePass`,
   use `Symfony\Component\Kernel\DependencyInjection\ResettableServicePass` instead
 * Removed `DependencyInjection\ServicesResetter`,
   use `Symfony\Component\Kernel\DependencyInjection\ServicesResetter` instead
 * Removed `EventListener\DumpListener`,
   use `Symfony\Component\Kernel\EventListener\DumpListener` instead
 * Removed `Log\Logger`,
   use `Symfony\Component\Kernel\Log\Logger` instead
 * Removed `RebootableInterface`,
   use `Symfony\Component\Kernel\RebootableInterface` instead
