UPGRADE FROM 4.2 to 4.3
=======================

BrowserKit
----------

 * Marked `Response` final.
 * Deprecated `Response::buildHeader()`
 * Deprecated `Response::getStatus()`, use `Response::getStatusCode()` instead

Cache
-----

 * The `psr/simple-cache` dependency has been removed - run `composer require psr/simple-cache` if you need it.
 * Deprecated all PSR-16 adapters, use `Psr16Cache` or `Symfony\Contracts\Cache\CacheInterface` implementations instead.
 * Deprecated `SimpleCacheAdapter`, use `Psr16Adapter instead.

Config
------

 * Deprecated using environment variables with `cannotBeEmpty()` if the value is validated with `validate()`

Form
----

 * Using names for buttons that do not start with a letter, a digit, or an underscore is deprecated and will lead to an
   exception in 5.0.
 * Using names for buttons that do not contain only letters, digits, underscores, hyphens, and colons is deprecated and
   will lead to an exception in 5.0.
 * Using the `date_format`, `date_widget`, and `time_widget` options of the `DateTimeType` when the `widget` option is
   set to `single_text` is deprecated.

FrameworkBundle
---------------

 * Not passing the project directory to the constructor of the `AssetsInstallCommand` is deprecated. This argument will
   be mandatory in 5.0.
 * Deprecated the "Psr\SimpleCache\CacheInterface" / "cache.app.simple" service, use "Symfony\Contracts\Cache\CacheInterface" / "cache.app" instead.

HttpFoundation
--------------

 * The `MimeTypeGuesserInterface` and `ExtensionGuesserInterface` interfaces have been deprecated,
   use `Symfony\Component\Mime\MimeTypesInterface` instead.
 * The `MimeType` and `MimeTypeExtensionGuesser` classes have been deprecated,
   use `Symfony\Component\Mime\MimeTypes` instead.
 * The `FileBinaryMimeTypeGuesser` class has been deprecated,
   use `Symfony\Component\Mime\FileBinaryMimeTypeGuesser` instead.
 * The `FileinfoMimeTypeGuesser` class has been deprecated,
   use `Symfony\Component\Mime\FileinfoMimeTypeGuesser` instead.
