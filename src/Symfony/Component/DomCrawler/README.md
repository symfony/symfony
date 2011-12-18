DomCrawler Component
====================

If you are familiar with jQuery, DomCrawler is a PHP equivalent.
It allows you to navigate the DOM of an HTML or XML document:

```
use Symfony\Component\DomCrawler\Crawler;

$crawler = new Crawler();
$crawler->addContent('<html><body><p>Hello World!</p></body></html>');

print $crawler->filterXPath('descendant-or-self::body/p')->text();
```

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/DomCrawler
