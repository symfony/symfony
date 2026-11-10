UPGRADE FROM 8.2 to 8.3
=======================

Symfony 8.3 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`. Make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.3/setup/upgrade_minor.html).

If you're upgrading from a version below 8.2, follow the [8.2 upgrade guide](UPGRADE-8.2.md) first.

ErrorHandler
------------

 * Deprecate `ErrorHandler::setDefaultLogger()`, `setLoggers()`, `throwAt()`, `scopeAt()`, `traceAt()` and `screamAt()`.
   These methods keep their current behavior in 8.x and will be removed in 9.0.
   Use `configure()` to set your PHP logger, deprecation logger and debug mode:

   ```php
   use Symfony\Component\ErrorHandler\ErrorHandler;

   $handler = ErrorHandler::register(new ErrorHandler(null, $debug));
   $handler->configure($logger, $deprecationLogger, $debug);
   ```

   Passing `null` as the deprecation logger sends deprecations to your PHP logger.
   Pass a `Psr\Log\NullLogger` instead to discard deprecation logs.
   Remove your old setter calls when switching to `configure()`.
   Loggers explicitly assigned with the old methods keep priority in 8.x.

   Pass `false` as the fourth argument to log warnings and notices instead of throwing:

   ```php
   $handler->configure($logger, $deprecationLogger, $debug, false);
   ```

   Pass `true` to convert reported PHP errors to `\ErrorException`, or `null` to keep the current conversion policy.
   Deprecations never throw; `E_USER_ERROR` and `E_RECOVERABLE_ERROR` always throw.
   To select PHP severities to log, pass a severity mask as the fifth argument.
   You can also pass a severity-to-log-level map:

   ```php
   use Psr\Log\LogLevel;

   $handler->configure($logger, $deprecationLogger, $debug, null, [
       E_WARNING => LogLevel::ERROR,
       E_USER_WARNING => LogLevel::ERROR,
       E_DEPRECATED => LogLevel::INFO,
       E_USER_DEPRECATED => LogLevel::INFO,
   ]);
   ```

   Replace custom per-severity logger assignments with a PSR-3 logger that delegates to your chosen loggers.
   PHP error records contain the error under `$context['exception']`.
   Call `getSeverity()` on `\ErrorException` or `SilencedErrorContext` to choose the logger.
   Handle other throwables as uncaught exceptions.

   Pass at least one logger to apply `$debug` to PHP error diagnostics.
   The constructor's `$debug` argument still controls fallback HTML rendering.
   Configure PHP's `zend.exception_ignore_args` setting to control argument collection by the engine.
   With `$debug` set to `false`, logged PHP errors omit trace arguments.
   Thrown errors keep the trace provided by PHP.
   `configure()` keeps the default tracing policy.
   Remove old `scopeAt()`, `traceAt()` and `screamAt()` calls to use the diagnostic defaults.
   In 8.x, switching to production diagnostics preserves previously enabled silenced-error collection.
   Arbitrary severity masks for conversion, argument retention, traces and silenced errors have no replacement.
   Keep such custom policies in your own error handler if you need them.

 * Add argument `$log` to `ErrorHandler::setExceptionHandler()`.
   Pass `false` when your custom exception handler logs exceptions itself:

   ```php
   $handler->setExceptionHandler($exceptionHandler, false);
   ```

   Configure logging before installing this callback.

   In 8.x, this also disables PHP error loggers for fatal errors and severities that are converted to exceptions.
