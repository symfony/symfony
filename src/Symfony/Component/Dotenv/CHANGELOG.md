CHANGELOG
=========

5.4
---

 * Add `dotenv:dump` command to compile the contents of the .env files into a PHP-optimized file called `.env.local.php`
 * Add `debug:dotenv` command to list all dotenv files with variables and values
 * Add `$overrideExistingVars` on `Dotenv::bootEnv()` and `Dotenv::loadEnv()`

5.1.0
-----

 * added `Dotenv::bootEnv()` to check for `.env.local.php` before calling `Dotenv::loadEnv()`
 * added `Dotenv::setProdEnvs()` and `Dotenv::usePutenv()`
 * made Dotenv's constructor accept `$envKey` and `$debugKey` arguments, to define
   the name of the env vars that configure the env name and debug settings
 * deprecated passing `$usePutenv` argument to Dotenv's constructor

5.0.0
-----

 * using `putenv()` is disabled by default

4.3.0
-----

 * deprecated use of `putenv()` by default. This feature will be opted-in with a constructor argument to `Dotenv`

4.2.0
-----

 * added `Dotenv::overload()` and `$overrideExistingVars` as optional parameter of `Dotenv::populate()`
 * added `Dotenv::loadEnv()` to load a .env file and its corresponding .env.local, .env.$env and .env.$env.local files if they exist

3.3.0
-----

 * [BC BREAK] Since v3.3.7, the latest Dotenv files override the previous ones. Real env vars are not affected and are not overridden.
 * added the component
