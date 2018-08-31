CHANGELOG
=========

4.3.0
-----

 * added `ConsoleCommandProcessor`: monolog processor that adds command name and arguments
 * added `RouteProcessor`: monolog processor that adds route name, controller::action and route params

4.2.0
-----

 * The methods `DebugProcessor::getLogs()`, `DebugProcessor::countErrors()`, `Logger::getLogs()`
   and `Logger::countErrors()` will have a new `$request` argument in version 5.0, not defining
   it is deprecated

4.1.0
-----

 * `WebProcessor` now implements `EventSubscriberInterface` in order to be easily autoconfigured

4.0.0
-----

 * the `$format`, `$dateFormat`, `$allowInlineLineBreaks`, and `$ignoreEmptyContextAndExtra`
   constructor arguments of the `ConsoleFormatter` class have been removed, use
   `$options` instead
 * the `DebugHandler` class has been removed

3.3.0
-----

 * Improved the console handler output formatting by adding var-dumper support

3.0.0
-----

 * deprecated interface `Symfony\Component\HttpKernel\Log\LoggerInterface` has been removed
 * deprecated methods `Logger::crit()`, `Logger::emerg()`, `Logger::err()` and `Logger::warn()` have been removed

2.4.0
-----

 * added ConsoleHandler and ConsoleFormatter which can be used to show log messages
   in the console output depending on the verbosity settings

2.1.0
-----

 * added ChromePhpHandler
