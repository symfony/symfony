Routing Component
=================

Routing associates a request with the code that will convert it to a response.

The example below demonstrates how you can set up a fully working routing
system:

    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\Routing\Matcher\UrlMatcher;
    use Symfony\Component\Routing\RequestContext;
    use Symfony\Component\Routing\RouteCollection;
    use Symfony\Component\Routing\Route;

    $routes = new RouteCollection();
    $routes->add('hello', new Route('/hello', array('controller' => 'foo')));

    $context = new RequestContext();

    // this is optional and can be done without a Request instance
    $context->fromRequest(Request::createFromGlobals());

    $matcher = new UrlMatcher($routes, $context);

    $parameters = $matcher->match('/hello');

Resources
---------

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Component/Routing/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export SYMFONY_CONFIG=../path/to/Config
    export SYMFONY_YAML=../path/to/Yaml
    export DOCTRINE_COMMON=../path/to/doctrine-common
