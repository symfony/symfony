DomCrawler Component
====================

DomCrawler eases DOM navigation for HTML and XML documents.

If you are familiar with jQuery, DomCrawler is a PHP equivalent:

```php
use Symfony\Component\DomCrawler\Crawler;

$crawler = new Crawler();
$crawler->addContent('<html><body><p>Hello World!</p></body></html>');

print $crawler->filterXPath('descendant-or-self::body/p')->text();
```

If you are also using the CssSelector component, you can use CSS Selectors
instead of XPath expressions:

```php
use Symfony\Component\DomCrawler\Crawler;

$crawler = new Crawler();
$crawler->addContent('<html><body><p>Hello World!</p></body></html>');

print $crawler->filter('body > p')->text();
```

Resources
---------

  * [Documentation](https://symfony.com/doc/current/components/dom_crawler.html)
  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
