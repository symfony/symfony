CHANGELOG
=========

4.2.0
-----

 * add bundle name suggestion on wrongly overridden templates paths
 * added `name` argument in `debug:twig` command and changed `filter` argument as `--filter` option
 * deprecated the `transchoice` tag and filter, use the `trans` ones instead with a `%count%` parameter

4.1.0
-----

 * add a `workflow_metadata` function

3.4.0
-----

 * added an `only` keyword to `form_theme` tag to disable usage of default themes when rendering a form
 * deprecated `Symfony\Bridge\Twig\Form\TwigRenderer`
 * deprecated `DebugCommand::set/getTwigEnvironment`. Pass an instance of
   `Twig\Environment` as first argument  of the constructor instead
 * deprecated `LintCommand::set/getTwigEnvironment`. Pass an instance of
   `Twig\Environment` as first argument of the constructor instead

3.3.0
-----

 * added a `workflow_has_marked_place` function
 * added a `workflow_marked_places` function

3.2.0
-----

 * added `AppVariable::getToken()`
 * Deprecated the possibility to inject the Form `TwigRenderer` into the `FormExtension`.
 * [BC BREAK] Registering the `FormExtension` without configuring a runtime loader for the `TwigRenderer`
   doesn't work anymore.

   Before:

   ```php
   use Symfony\Bridge\Twig\Extension\FormExtension;
   use Symfony\Bridge\Twig\Form\TwigRenderer;
   use Symfony\Bridge\Twig\Form\TwigRendererEngine;

   // ...
   $rendererEngine = new TwigRendererEngine(array('form_div_layout.html.twig'));
   $rendererEngine->setEnvironment($twig);
   $twig->addExtension(new FormExtension(new TwigRenderer($rendererEngine, $csrfTokenManager)));
   ```

   After:

   ```php
   // ...
   $rendererEngine = new TwigRendererEngine(array('form_div_layout.html.twig'), $twig);
   // require Twig 1.30+
   $twig->addRuntimeLoader(new \Twig\RuntimeLoader\FactoryRuntimeLoader(array(
       TwigRenderer::class => function () use ($rendererEngine, $csrfTokenManager) {
           return new TwigRenderer($rendererEngine, $csrfTokenManager);
       },
   )));
   $twig->addExtension(new FormExtension());
   ```
 * Deprecated the `TwigRendererEngineInterface` interface.
 * added WorkflowExtension (provides `workflow_can` and `workflow_transitions`)

2.7.0
-----

 * added LogoutUrlExtension (provides `logout_url` and `logout_path`)
 * added an HttpFoundation extension (provides the `absolute_url` and the `relative_path` functions)
 * added AssetExtension (provides the `asset` and `asset_version` functions)
 * Added possibility to extract translation messages from a file or files besides extracting from a directory

2.5.0
-----

 * moved command `twig:lint` from `TwigBundle`

2.4.0
-----

 * added stopwatch tag to time templates with the WebProfilerBundle

2.3.0
-----

 * added helpers form(), form_start() and form_end()
 * deprecated form_enctype() in favor of form_start()

2.2.0
-----

 * added a `controller` function to help generating controller references
 * added a `render_esi` and a `render_hinclude` function
 * [BC BREAK] restricted the `render` tag to only accept URIs or ControllerReference instances (the signature changed)
 * added a `render` function to render a request
 * The `app` global variable is now injected even when using the twig service directly.
 * Added an optional parameter to the `path` and `url` function which allows to generate
   relative paths (e.g. "../parent-file") and scheme-relative URLs (e.g. "//example.com/dir/file").

2.1.0
-----

 * added global variables access in a form theme
 * added TwigEngine
 * added TwigExtractor
 * added a csrf_token function
 * added a way to specify a default domain for a Twig template (via the
   'trans_default_domain' tag)
