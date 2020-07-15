UPGRADE FROM 5.2 to 5.3
=======================

HttpKernel
----------

 * Deprecated the `Kernel::$environment` property, use `Kernel::$mode` instead
 * Deprecated the `KernelInterface::getEnvironment()` method, use `KernelInterface::getMode()` instead
 * Deprecated the `ConfigDataCollector::getEnv()` method, use `ConfigDataCollector::getMode()` instead
