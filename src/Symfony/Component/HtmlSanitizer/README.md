HtmlSanitizer Component
=======================

The HtmlSanitizer component provides an object-oriented API to sanitize
untrusted HTML input for safe insertion into a document's DOM.

Usage
-----

```php
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;

// By default, an element not added to the allowed or blocked elements
// will be dropped, including its children
$config = (new HtmlSanitizerConfig())
    // Allow "safe" elements and attributes. All scripts will be removed
    // as well as other dangerous behaviors like CSS injection
    ->allowSafeElements()

    // Allow all static elements and attributes from the W3C Sanitizer API
    // standard. All scripts will be removed but the output may still contain
    // other dangerous behaviors like CSS injection (click-jacking), CSS
    // expressions, ...
    ->allowStaticElements()

    // Allow the "div" element and no attribute can be on it
    ->allowElement('div')

    // Allow the "a" element, and the "title" attribute to be on it
    ->allowElement('a', ['title'])

    // Allow the "span" element, and any attribute from the Sanitizer API is allowed
    // (see https://wicg.github.io/sanitizer-api/#default-configuration)
    ->allowElement('span', '*')

    // Block the "section" element: this element will be removed but
    // its children will be retained
    ->blockElement('section')

    // Drop the "div" element: this element will be removed, including its children
    ->dropElement('div')

    // Allow the attribute "title" on the "div" element
    ->allowAttribute('title', ['div'])

    // Allow the attribute "data-custom-attr" on all currently allowed elements
    ->allowAttribute('data-custom-attr', '*')

    // Drop the "data-custom-attr" attribute from the "div" element:
    // this attribute will be removed
    ->dropAttribute('data-custom-attr', ['div'])

    // Drop the "data-custom-attr" attribute from all elements:
    // this attribute will be removed
    ->dropAttribute('data-custom-attr', '*')

    // Forcefully set the value of all "rel" attributes on "a"
    // elements to "noopener noreferrer"
    ->forceAttribute('a', 'rel', 'noopener noreferrer')

    // Transform all HTTP schemes to HTTPS
    ->forceHttpsUrls()

    // Configure which schemes are allowed in links (others will be dropped)
    ->allowLinkSchemes(['https', 'http', 'mailto'])

    // Configure which hosts are allowed in links (by default all are allowed)
    ->allowLinkHosts(['symfony.com', 'example.com'])

    // Allow relative URL in links (by default they are dropped)
    ->allowRelativeLinks()

    // Configure which schemes are allowed in img/audio/video/iframe (others will be dropped)
    ->allowMediaSchemes(['https', 'http'])

    // Configure which hosts are allowed in img/audio/video/iframe (by default all are allowed)
    ->allowMediaHosts(['symfony.com', 'example.com'])

    // Allow relative URL in img/audio/video/iframe (by default they are dropped)
    ->allowRelativeMedias()

    // Configure a custom attribute sanitizer to apply custom sanitization logic
    // ($attributeSanitizer instance of AttributeSanitizerInterface)
    ->withAttributeSanitizer($attributeSanitizer)

    // Unregister a previously registered attribute sanitizer
    // ($attributeSanitizer instance of AttributeSanitizerInterface)
    ->withoutAttributeSanitizer($attributeSanitizer)
;

$sanitizer = new HtmlSanitizer($config);

// Sanitize a given string, using the configuration provided and in the
// "body" context (tags only allowed in <head> will be removed)
$sanitizer->sanitize($userInput);

// Sanitize the given string for a usage in a <head> tag
$sanitizer->sanitizeFor('head', $userInput);

// Sanitize the given string for a usage in another tag
$sanitizer->sanitizeFor('title', $userInput); // Will encode as HTML entities
$sanitizer->sanitizeFor('textarea', $userInput); // Will encode as HTML entities
$sanitizer->sanitizeFor('div', $userInput); // Will sanitize as body
$sanitizer->sanitizeFor('section', $userInput); // Will sanitize as body
// ...
```

Resources
---------

* [Contributing](https://symfony.com/doc/current/contributing/index.html)
* [Report issues](https://github.com/symfony/symfony/issues) and
  [send Pull Requests](https://github.com/symfony/symfony/pulls)
  in the [main Symfony repository](https://github.com/symfony/symfony)
