CHANGELOG
=========

5.4
---

 * Add `Crawler::innerText` method.

5.3
---

 * The `parents()` method is deprecated. Use `ancestors()` instead.
 * Marked the `containsOption()`, `availableOptionValues()`, and `disableValidation()` methods of the
   `ChoiceFormField` class as internal

5.1.0
-----

 * Added an internal cache layer on top of the CssSelectorConverter
 * Added `UriResolver` to resolve an URI according to a base URI

5.0.0
-----

 * Added argument `$selector` to `Crawler::children()`
 * Added argument `$default` to `Crawler::text()` and `html()`

4.4.0
-----

 * Added `Form::getName()` method.
 * Added `Crawler::matches()` method.
 * Added `Crawler::closest()` method.
 * Added `Crawler::outerHtml()` method.
 * Added an argument to the `Crawler::text()` method to opt-in normalizing whitespaces.

4.3.0
-----

 * Added PHPUnit constraints: `CrawlerSelectorAttributeValueSame`, `CrawlerSelectorExists`, `CrawlerSelectorTextContains`
   and `CrawlerSelectorTextSame`
 * Added return of element name (`_name`) in `extract()` method.
 * Added ability to return a default value in `text()` and `html()` instead of throwing an exception when node is empty.
 * When available, the [html5-php library](https://github.com/Masterminds/html5-php) is used to
   parse HTML added to a Crawler for better support of HTML5 tags.

4.2.0
-----

 * The `$currentUri` constructor argument of the `AbstractUriElement`, `Link` and
   `Image` classes is now optional.
 * The `Crawler::children()` method will have a new `$selector` argument in version 5.0,
   not defining it is deprecated.

3.1.0
-----

 * All the URI parsing logic have been abstracted in the `AbstractUriElement` class.
   The `Link` class is now a child of `AbstractUriElement`.
 * Added an `Image` class to crawl images and parse their `src` attribute,
   and `selectImage`, `image`, `images` methods in the `Crawler` (the image version of the equivalent `link` methods).

2.5.0
-----

 * [BC BREAK] The default value for checkbox and radio inputs without a value attribute have changed
   from '1' to 'on' to match the HTML specification.
 * [BC BREAK] The typehints on the `Link`, `Form` and `FormField` classes have been changed from
   `\DOMNode` to `DOMElement`. Using any other type of `DOMNode` was triggering fatal errors in previous
   versions. Code extending these classes will need to update the typehints when overwriting these methods.

2.4.0
-----

 * `Crawler::addXmlContent()` removes the default document namespace again if it's an only namespace.
 * added support for automatic discovery and explicit registration of document
   namespaces for `Crawler::filterXPath()` and `Crawler::filter()`
 * improved content type guessing in `Crawler::addContent()`
 * [BC BREAK] `Crawler::addXmlContent()` no longer removes the default document
   namespace

2.3.0
-----

 * added Crawler::html()
 * [BC BREAK] Crawler::each() and Crawler::reduce() now return Crawler instances instead of DomElement instances
 * added schema relative URL support to links
 * added support for HTML5 'form' attribute

2.2.0
-----

 * added a way to set raw path to the file in FileFormField - necessary for
   simulating HTTP requests

2.1.0
-----

 * added support for the HTTP PATCH method
 * refactored the Form class internals to support multi-dimensional fields
   (the public API is backward compatible)
 * added a way to get parsing errors for Crawler::addHtmlContent() and
   Crawler::addXmlContent() via libxml functions
 * added support for submitting a form without a submit button
