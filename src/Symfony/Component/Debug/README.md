Debug Component
===============

Debug provides tools to make debugging easier.

Here is classic usage of the main provided tools::

    use Symfony\Component\Debug\ErrorHandler;
    use Symfony\Component\Debug\ExceptionHandler;

    error_reporting(-1);

    ErrorHandler::register($this->errorReportingLevel);
    if ('cli' !== php_sapi_name()) {
        ExceptionHandler::register();
    } elseif (!ini_get('log_errors') || ini_get('error_log')) {
        ini_set('display_errors', 1);
    }

    // from the ClassLoader component
    DebugClassLoader::enable();

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/Debug/
    $ composer.phar install --dev
    $ phpunit
