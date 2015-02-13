CHANGELOG
=========

2.7.0
-----

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
