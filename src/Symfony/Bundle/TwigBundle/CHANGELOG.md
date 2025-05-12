CHANGELOG
=========

7.3
---

 * Enable `#[AsTwigFilter]`, `#[AsTwigFunction]` and `#[AsTwigTest]` attributes
   to configure extensions on runtime classes
 * Add support for a `twig` validator
 * Use `ChainCache` to store warmed-up cache in `kernel.build_dir` and runtime cache in `kernel.cache_dir`
 * Make `TemplateCacheWarmer` use `kernel.build_dir` instead of `kernel.cache_dir`

7.1
---

 * Mark class `TemplateCacheWarmer` as `final`

7.0
---

 * Remove the `Twig_Environment` autowiring alias, use `Twig\Environment` instead
 * Remove option `twig.autoescape`; create a class that implements your escaping strategy
   (check `FileExtensionEscapingStrategy::guess()` for inspiration) and reference it using
   the `twig.autoescape_service` option instead
 * Drop support for Twig 2

6.4
---

 * Allow omitting the `autoescape_service_method` option when `autoescape_service` is set to an invokable service id

6.3
---

 * Deprecate the `Twig_Environment` autowiring alias, use `Twig\Environment` instead

6.2
---

 * Add the `twig.mailer.html_to_text_converter` option to allow configuring custom `HtmlToTextConverterInterface`
   implementations to be used by the `twig.mime_body_renderer` service

6.1
---

 * Add option `twig.file_name_pattern` to restrict which files are compiled by cache warmer and linter
 * Deprecate option `twig.autoescape`, use `twig.autoescape_service[_method]` instead

6.0
---

 * The `twig` service is now private

5.3
---

 * Add support for the new `serialize` filter (from Twig Bridge)

5.2.0
-----

 * deprecated the public `twig` service to private

5.0.0
-----

 * updated default value for the `strict_variables` option to `%kernel.debug%` parameter
 * removed support to load templates from the legacy directories `src/Resources/views/` and `src/Resources/<BundleName>/views/`
 * removed `TwigEngine` class, use `Twig\Environment` instead
 * removed `FilesystemLoader` and `NativeFilesystemLoader`, use Twig notation for templates instead
 * removed `twig.exception_controller` configuration option, use `framework.error_controller` option instead
 * removed `ExceptionController`, `PreviewErrorController` and all built-in error templates in favor of the new error renderer mechanism

4.4.0
-----

 * marked the `TemplateIterator` as `internal`
 * added HTML comment to beginning and end of `exception_full.html.twig`
 * deprecated `ExceptionController` and `PreviewErrorController` controllers, use `ErrorController` from the `HttpKernel` component instead
 * deprecated all built-in error templates in favor of the new error renderer mechanism
 * deprecated `twig.exception_controller` configuration option, set it to "null" and use `framework.error_controller` configuration instead

4.2.0
-----

 * deprecated support for legacy templates directories `src/Resources/views/` and `src/Resources/<BundleName>/views/`, use `templates/` and `templates/bundles/<BundleName>/` instead.

4.1.0
-----

 * added priority to Twig extensions
 * deprecated relying on the default value (`false`) of the `twig.strict_variables` configuration option. The `%kernel.debug%` parameter will be the new default in 5.0

4.0.0
-----

 * removed `ContainerAwareRuntimeLoader`

3.4.0
-----

 * added exclusive Twig namespace only for root bundles
 * deprecated `Symfony\Bundle\TwigBundle\Command\DebugCommand`, use `Symfony\Bridge\Twig\Command\DebugCommand` instead
 * deprecated relying on the `ContainerAwareInterface` implementation for `Symfony\Bundle\TwigBundle\Command\LintCommand`
 * added option to configure default path templates (via `default_path`)

3.3.0
-----

 * Deprecated `ContainerAwareRuntimeLoader`

2.7.0
-----

 * made it possible to configure the default formats for both the `date` and the `number_format` filter
 * added support for the new Asset component (from Twig bridge)
 * deprecated the assets extension (use the one from the Twig bridge instead)

2.6.0
-----

 * [BC BREAK] changed exception.json.twig to match same structure as error.json.twig making clients independent of runtime environment.

2.3.0
-----

 * added option to configure a custom template escaping guesser (via `autoescape_service` and `autoescape_service_method`)

2.2.0
-----

 * moved the exception controller to be a service (`twig.controller.exception:showAction` vs `Symfony\\Bundle\\TwigBundle\\Controller\\ExceptionController::showAction`)
 * added support for multiple loaders via the "twig.loader" tag.
 * added automatic registration of namespaced paths for registered bundles
 * added support for namespaced paths

2.1.0
-----

 * added a new setting ("paths") to configure more paths for the Twig filesystem loader
 * added contextual escaping based on the template file name (disabled if you explicitly pass an autoescape option)
 * added a command that extracts translation messages from templates
 * added the real template name when an error occurs in a Twig template
 * added the twig:lint command that will validate a Twig template syntax.
