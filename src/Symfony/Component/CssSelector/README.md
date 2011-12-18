CssSelector Component
=====================

This component is a port of the Python lxml library, which is copyright Infrae
and distributed under the BSD license.

Current code is a port of http://codespeak.net/svn/lxml/trunk/src/lxml/cssselect.py@71545

Using CSS selectors is far easier than using XPath.

Its only goal is to convert a CSS selector to its XPath equivalent:

```
use Symfony\Component\CssSelector\CssSelector;

print CssSelector::toXPath('div.item > h4 > a');
```

That way, you can just use CSS Selectors with the DomCrawler instead of XPath:

```
use Symfony\Component\DomCrawler\Crawler;

$crawler = new Crawler();
$crawler->addContent('<html><body><p>Hello World!</p></body></html>');

print $crawler->filter('body > p')->text();
```

By the way, that's one example of a component (DomCrawler) that relies
on another one (CssSelector) for some optional features.

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/CssSelector
