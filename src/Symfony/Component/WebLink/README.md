WebLink Component
=================

The WebLink component manages links between resources. It is particularly useful to advise clients
to preload and prefetch documents through HTTP and HTTP/2 pushes.

This component implements the [HTML5's Links](https://www.w3.org/TR/html5/links.html), [Preload](https://www.w3.org/TR/preload/)
and [Resource Hints](https://www.w3.org/TR/resource-hints/) W3C's specifications.
It can also be used with extensions defined in the [HTML5 link type extensions wiki](http://microformats.org/wiki/existing-rel-values#HTML5_link_type_extensions).

Getting Started
---------------

```
$ composer require symfony/web-link
```

```php
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\Link;

$linkProvider = (new GenericLinkProvider())
    ->withLink(new Link('preload', '/bootstrap.min.css'));

header('Link: '.(new HttpHeaderSerializer())->serialize($linkProvider->getLinks()));

echo 'Hello';
```

Resources
---------

  * [Documentation](https://symfony.com/doc/current/web_link.html)
  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
