Locale Component
================

Locale provides fallback code to handle cases when the ``intl`` extension is
missing.

Loading the fallback classes for example using the ClassLoader component only
requires adding the following lines to your autoloader:

    // intl
    if (!function_exists('intl_get_error_code')) {
        require __DIR__.'/../vendor/symfony/src/Symfony/Component/Locale/Resources/stubs/functions.php';

        $loader->registerPrefixFallbacks(array(__DIR__.'/../vendor/symfony/src/Symfony/Component/Locale/Resources/stubs'));
    }

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/Locale
