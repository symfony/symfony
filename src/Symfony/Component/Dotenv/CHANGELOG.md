CHANGELOG
=========

4.2.0
-----

 * added `Dotenv::overload()` and `$overrideExistingVars` as optional parameter of `Dotenv::populate()`
 * added `Dotenv::loadEnv()` to load a .env file and its corresponding .env.local, .env.$env and .env.$env.local files if they exist

3.3.0
-----

 * [BC BREAK] Since v3.3.7, the latest Dotenv files override the previous ones. Real env vars are not affected and are not overridden.
 * added the component
