CssSelector Component
=====================

CssSelector converts CSS selectors to XPath expressions.

The component only goal is to convert CSS selectors to their XPath
equivalents:

    use Symfony\Component\CssSelector\CssSelector;

    print CssSelector::toXPath('div.item > h4 > a');

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

This component is a port of the Python lxml library, which is copyright Infrae
and distributed under the BSD license.

Current code is a port of https://github.com/SimonSapin/cssselect@v0.7.1

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/CssSelector/
    $ composer.phar install --dev
    $ phpunit
