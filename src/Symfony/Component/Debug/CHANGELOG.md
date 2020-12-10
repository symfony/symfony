CHANGELOG
=========

4.4.0
-----

 * deprecated `FlattenException`, use the `FlattenException` of the `ErrorHandler` component
 * deprecated the whole component in favor of the `ErrorHandler` component

4.3.0
-----

 * made the `ErrorHandler` and `ExceptionHandler` classes final
 * added `Exception\FlattenException::getAsString` and
   `Exception\FlattenException::getTraceAsString` to increase compatibility to php
   exception objects

4.0.0
-----

 * removed the symfony_debug extension
 * removed `ContextErrorException`

3.4.0
-----

 * deprecated `ErrorHandler::stackErrors()` and `ErrorHandler::unstackErrors()`

3.3.0
-----

 * deprecated the `ContextErrorException` class: use \ErrorException directly now

3.2.0
-----

 * `FlattenException::getTrace()` now returns additional type descriptions
   `integer` and `float`.

3.0.0
-----

 * removed classes, methods and interfaces deprecated in 2.x

2.8.0
-----

 * added BufferingLogger for errors that happen before a proper logger is configured
 * allow throwing from `__toString()` with `return trigger_error($e, E_USER_ERROR);`
 * deprecate ExceptionHandler::createResponse

2.7.0
-----

 * added deprecations checking for parent interfaces/classes to DebugClassLoader
 * added ZTS support to symfony_debug extension
 * added symfony_debug_backtrace() to symfony_debug extension
   to track the backtrace of fatal errors

2.6.0
-----

 * generalized ErrorHandler and ExceptionHandler,
   with some new methods and others deprecated
 * enhanced error messages for uncaught exceptions

2.5.0
-----

 * added ExceptionHandler::setHandler()
 * added UndefinedMethodFatalErrorHandler
 * deprecated DummyException

2.4.0
-----

 * added a DebugClassLoader able to wrap any autoloader providing a findFile method
 * improved error messages for not found classes and functions

2.3.0
-----

 * added the component
