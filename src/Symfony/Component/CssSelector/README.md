CssSelector Component
=====================

The CssSelector component converts CSS selectors to XPath expressions.

HTML and XML are different
--------------------------

- The `CssSelector` component comes with an `HTML` extension which is enabled by default.
- If you need to use this component with `XML` documents, you have to disable `HTML` extension.
- `HTML` tag & attribute names are always lower-cased, with `XML` they are case-sensistive.

Disable & enable `HTML` extension:

    // disable `HTML` extension:
    CssSelector::disableHtmlExtension();
    // re-enable `HTML` extension:
    CssSelector::enableHtmlExtension();

What brings `HTML` extension?
- Tag names are lower-cased
- Attribute names are lower-cased
- Adds following pseudo-classes:
    - `checked`, `link`, `disabled`, `enabled`, `selected`: used with form tags
    - `invalid`, `hover`, `visited`: always select nothing
- Adds `lang()` function

Resources
---------

  * [Documentation](https://symfony.com/doc/current/components/css_selector.html)
  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)

Credits
-------

This component is a port of the Python cssselect library
[v0.7.1](https://github.com/SimonSapin/cssselect/releases/tag/v0.7.1),
which is distributed under the BSD license.
