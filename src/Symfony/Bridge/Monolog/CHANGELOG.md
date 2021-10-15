CHANGELOG
=========

5.4
---

 * Deprecate `ResetLoggersWorkerSubscriber` to reset buffered logs in messenger
   workers, use "reset_on_message" option in messenger configuration instead.

5.3
---

 * Add `ResetLoggersWorkerSubscriber` to reset buffered logs in messenger workers

5.2.0
-----

 * The `$actionLevel` constructor argument of `Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy` has been deprecated and replaced by the `$inner` one which expects an ActivationStrategyInterface to decorate instead. `Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy` will become final in 6.0.
 * The `$actionLevel` constructor argument of `Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy` has been deprecated and replaced by the `$inner` one which expects an ActivationStrategyInterface to decorate instead. `Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy` will become final in 6.0

5.1.0
-----

 * Added `MailerHandler`

5.0.0
-----

 * The methods `DebugProcessor::getLogs()`, `DebugProcessor::countErrors()`, `Logger::getLogs()` and `Logger::countErrors()` have a new `$request` argument.
 * Added support for Monolog 2.

4.4.0
-----

 * The `RouteProcessor` class has been made final
 * Added `ElasticsearchLogstashHandler`
 * Added the `ServerLogCommand`. Backport from the deprecated WebServerBundle

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
