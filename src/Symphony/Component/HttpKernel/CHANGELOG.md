CHANGELOG
=========

4.1.0
-----

 * added orphaned events support to `EventDataCollector`
 * `ExceptionListener` now logs and collects exceptions at priority `2048` (previously logged at `-128` and collected at `0`)
 * Deprecated `service:action` syntax with a single colon to reference controllers. Use `service::method` instead.

4.0.0
-----

 * removed the `DataCollector::varToString()` method, use `DataCollector::cloneVar()`
   instead
 * using the `DataCollector::cloneVar()` method requires the VarDumper component
 * removed the `ValueExporter` class
 * removed `ControllerResolverInterface::getArguments()`
 * removed `TraceableControllerResolver::getArguments()`
 * removed `ControllerResolver::getArguments()` and the ability to resolve arguments
 * removed the `argument_resolver` service dependency from the `debug.controller_resolver`
 * removed `LazyLoadingFragmentHandler::addRendererService()`
 * removed `Psr6CacheClearer::addPool()`
 * removed `Extension::addClassesToCompile()` and `Extension::getClassesToCompile()`
 * removed `Kernel::loadClassCache()`, `Kernel::doLoadClassCache()`, `Kernel::setClassCache()`,
   and `Kernel::getEnvParameters()`
 * support for the `X-Status-Code` when handling exceptions in the `HttpKernel`
   has been dropped, use the `HttpKernel::allowCustomResponseCode()` method
   instead
 * removed convention-based commands registration
 * removed the `ChainCacheClearer::add()` method
 * removed the `CacheaWarmerAggregate::add()` and `setWarmers()` methods
 * made `CacheWarmerAggregate` and `ChainCacheClearer` classes final

3.4.0
-----

 * added a minimalist PSR-3 `Logger` class that writes in `stderr`
 * made kernels implementing `CompilerPassInterface` able to process the container
 * deprecated bundle inheritance
 * added `RebootableInterface` and implemented it in `Kernel`
 * deprecated commands auto registration
 * deprecated `EnvParametersResource`
 * added `Symphony\Component\HttpKernel\Client::catchExceptions()`
 * deprecated the `ChainCacheClearer::add()` method
 * deprecated the `CacheaWarmerAggregate::add()` and `setWarmers()` methods
 * made `CacheWarmerAggregate` and `ChainCacheClearer` classes final
 * added the possibility to reset the profiler to its initial state
 * deprecated data collectors without a `reset()` method
 * deprecated implementing `DebugLoggerInterface` without a `clear()` method

3.3.0
-----

 * added `kernel.project_dir` and `Kernel::getProjectDir()`
 * deprecated `kernel.root_dir` and `Kernel::getRootDir()`
 * deprecated `Kernel::getEnvParameters()`
 * deprecated the special `SYMPHONY__` environment variables
 * added the possibility to change the query string parameter used by `UriSigner`
 * deprecated `LazyLoadingFragmentHandler::addRendererService()`
 * deprecated `Extension::addClassesToCompile()` and `Extension::getClassesToCompile()`
 * deprecated `Psr6CacheClearer::addPool()`

3.2.0
-----

 * deprecated `DataCollector::varToString()`, use `cloneVar()` instead
 * changed surrogate capability name in `AbstractSurrogate::addSurrogateCapability` to 'symphony'
 * Added `ControllerArgumentValueResolverPass`

3.1.0
-----
 * deprecated passing objects as URI attributes to the ESI and SSI renderers
 * deprecated `ControllerResolver::getArguments()`
 * added `Symphony\Component\HttpKernel\Controller\ArgumentResolverInterface`
 * added `Symphony\Component\HttpKernel\Controller\ArgumentResolverInterface` as argument to `HttpKernel`
 * added `Symphony\Component\HttpKernel\Controller\ArgumentResolver`
 * added `Symphony\Component\HttpKernel\DataCollector\RequestDataCollector::getMethod()`
 * added `Symphony\Component\HttpKernel\DataCollector\RequestDataCollector::getRedirect()`
 * added the `kernel.controller_arguments` event, triggered after controller arguments have been resolved

3.0.0
-----

 * removed `Symphony\Component\HttpKernel\Kernel::init()`
 * removed `Symphony\Component\HttpKernel\Kernel::isClassInActiveBundle()` and `Symphony\Component\HttpKernel\KernelInterface::isClassInActiveBundle()`
 * removed `Symphony\Component\HttpKernel\Debug\TraceableEventDispatcher::setProfiler()`
 * removed `Symphony\Component\HttpKernel\EventListener\FragmentListener::getLocalIpAddresses()`
 * removed `Symphony\Component\HttpKernel\EventListener\LocaleListener::setRequest()`
 * removed `Symphony\Component\HttpKernel\EventListener\RouterListener::setRequest()`
 * removed `Symphony\Component\HttpKernel\EventListener\ProfilerListener::onKernelRequest()`
 * removed `Symphony\Component\HttpKernel\Fragment\FragmentHandler::setRequest()`
 * removed `Symphony\Component\HttpKernel\HttpCache\Esi::hasSurrogateEsiCapability()`
 * removed `Symphony\Component\HttpKernel\HttpCache\Esi::addSurrogateEsiCapability()`
 * removed `Symphony\Component\HttpKernel\HttpCache\Esi::needsEsiParsing()`
 * removed `Symphony\Component\HttpKernel\HttpCache\HttpCache::getEsi()`
 * removed `Symphony\Component\HttpKernel\DependencyInjection\ContainerAwareHttpKernel`
 * removed `Symphony\Component\HttpKernel\DependencyInjection\RegisterListenersPass`
 * removed `Symphony\Component\HttpKernel\EventListener\ErrorsLoggerListener`
 * removed `Symphony\Component\HttpKernel\EventListener\EsiListener`
 * removed `Symphony\Component\HttpKernel\HttpCache\EsiResponseCacheStrategy`
 * removed `Symphony\Component\HttpKernel\HttpCache\EsiResponseCacheStrategyInterface`
 * removed `Symphony\Component\HttpKernel\Log\LoggerInterface`
 * removed `Symphony\Component\HttpKernel\Log\NullLogger`
 * removed `Symphony\Component\HttpKernel\Profiler::import()`
 * removed `Symphony\Component\HttpKernel\Profiler::export()`

2.8.0
-----

 * deprecated `Profiler::import` and `Profiler::export`

2.7.0
-----

 * added the HTTP status code to profiles

2.6.0
-----

 * deprecated `Symphony\Component\HttpKernel\EventListener\ErrorsLoggerListener`, use `Symphony\Component\HttpKernel\EventListener\DebugHandlersListener` instead
 * deprecated unused method `Symphony\Component\HttpKernel\Kernel::isClassInActiveBundle` and `Symphony\Component\HttpKernel\KernelInterface::isClassInActiveBundle`

2.5.0
-----

 * deprecated `Symphony\Component\HttpKernel\DependencyInjection\RegisterListenersPass`, use `Symphony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass` instead

2.4.0
-----

 * added event listeners for the session
 * added the KernelEvents::FINISH_REQUEST event

2.3.0
-----

 * [BC BREAK] renamed `Symphony\Component\HttpKernel\EventListener\DeprecationLoggerListener` to `Symphony\Component\HttpKernel\EventListener\ErrorsLoggerListener` and changed its constructor
 * deprecated `Symphony\Component\HttpKernel\Debug\ErrorHandler`, `Symphony\Component\HttpKernel\Debug\ExceptionHandler`,
   `Symphony\Component\HttpKernel\Exception\FatalErrorException` and `Symphony\Component\HttpKernel\Exception\FlattenException`
 * deprecated `Symphony\Component\HttpKernel\Kernel::init()`
 * added the possibility to specify an id an extra attributes to hinclude tags
 * added the collect of data if a controller is a Closure in the Request collector
 * pass exceptions from the ExceptionListener to the logger using the logging context to allow for more
   detailed messages

2.2.0
-----

 * [BC BREAK] the path info for sub-request is now always _fragment (or whatever you configured instead of the default)
 * added Symphony\Component\HttpKernel\EventListener\FragmentListener
 * added Symphony\Component\HttpKernel\UriSigner
 * added Symphony\Component\HttpKernel\FragmentRenderer and rendering strategies (in Symphony\Component\HttpKernel\Fragment\FragmentRendererInterface)
 * added Symphony\Component\HttpKernel\DependencyInjection\ContainerAwareHttpKernel
 * added ControllerReference to create reference of Controllers (used in the FragmentRenderer class)
 * [BC BREAK] renamed TimeDataCollector::getTotalTime() to
   TimeDataCollector::getDuration()
 * updated the MemoryDataCollector to include the memory used in the
   kernel.terminate event listeners
 * moved the Stopwatch classes to a new component
 * added TraceableControllerResolver
 * added TraceableEventDispatcher (removed ContainerAwareTraceableEventDispatcher)
 * added support for WinCache opcode cache in ConfigDataCollector

2.1.0
-----

 * [BC BREAK] the charset is now configured via the Kernel::getCharset() method
 * [BC BREAK] the current locale for the user is not stored anymore in the session
 * added the HTTP method to the profiler storage
 * updated all listeners to implement EventSubscriberInterface
 * added TimeDataCollector
 * added ContainerAwareTraceableEventDispatcher
 * moved TraceableEventDispatcherInterface to the EventDispatcher component
 * added RouterListener, LocaleListener, and StreamedResponseListener
 * added CacheClearerInterface (and ChainCacheClearer)
 * added a kernel.terminate event (via TerminableInterface and PostResponseEvent)
 * added a Stopwatch class
 * added WarmableInterface
 * improved extensibility between bundles
 * added profiler storages for Memcache(d), File-based, MongoDB, Redis
 * moved Filesystem class to its own component
