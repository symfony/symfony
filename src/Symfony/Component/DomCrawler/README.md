DomCrawler Component
====================

DomCrawler eases DOM navigation for HTML and XML documents.

If you are familiar with jQuery, DomCrawler is a PHP equivalent:

    use Symfony\Component\DomCrawler\Crawler;

    $crawler = new Crawler();
    $crawler->addContent('<html><body><p>Hello World!</p></body></html>');

    print $crawler->filterXPath('descendant-or-self::body/p')->text();

If you are also using the CssSelector component, you can use CSS Selectors
instead of XPath expressions:

    use Symfony\Component\DomCrawler\Crawler;

    $crawler = new Crawler();
    $crawler->addContent('<html><body><p>Hello World!</p></body></html>');

    print $crawler->filter('body > p')->text();

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/DomCrawler
