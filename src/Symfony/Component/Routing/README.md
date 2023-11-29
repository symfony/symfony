Routing Component
=================

The Routing component maps an HTTP request to a set of configuration variables.

Getting Started
---------------

```
$ composer require symfony/routing
```

```php
use App\Controller\BlogController;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$route = new Route('/blog/{slug}', ['_controller' => BlogController::class]);
$routes = new RouteCollection();
$routes->add('blog_show', $route);

$context = new RequestContext();

// Routing can match routes with incoming requests
$matcher = new UrlMatcher($routes, $context);
$parameters = $matcher->match('/blog/lorem-ipsum');
// $parameters = [
//     '_controller' => 'App\Controller\BlogController',
//     'slug' => 'lorem-ipsum',
//     '_route' => 'blog_show'
// ]

// Routing can also generate URLs for a given route
$generator = new UrlGenerator($routes, $context);
$url = $generator->generate('blog_show', [
    'slug' => 'my-blog-post',
]);
// $url = '/blog/my-blog-post'
```

Sponsor
-------

The Routing component for Symfony 7.0 is [backed][1] by [redirection.io][2].

redirection.io logs all your website’s HTTP traffic, and lets you fix errors
with redirect rules in seconds. Give your marketing, SEO and IT teams the
right tool to manage your website traffic efficiently!

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Documentation](https://symfony.com/doc/current/routing.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[2]: https://redirection.io
[3]: https://symfony.com/sponsor
