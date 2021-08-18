CHANGELOG
=========

5.4
---

 * Make `DebugClassLoader` trigger deprecation notices on missing return types
 * Add `SYMFONY_PATCH_TYPE_DECLARATIONS='force=2'` mode to `DebugClassLoader` to turn annotations into native return types

5.2.0
-----

 * added the ability to set `HtmlErrorRenderer::$template` to a custom template to render when not in debug mode.

5.1.0
-----

 * The `HtmlErrorRenderer` and `SerializerErrorRenderer` add `X-Debug-Exception` and `X-Debug-Exception-File` headers in debug mode.

4.4.0
-----

 * added the component
 * added `ErrorHandler::call()` method utility to turn any PHP error into `\ErrorException`
