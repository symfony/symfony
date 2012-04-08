HttpKernel Component
====================

HttpKernel provides the building blocks to create flexible and fast HTTP-based
frameworks.

``HttpKernelInterface`` is the core interface of the Symfony2 full-stack
framework:

    interface HttpKernelInterface
    {
        /**
         * Handles a Request to convert it to a Response.
         *
         * @param  Request $request A Request instance
         *
         * @return Response A Response instance
         */
        function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true);
    }

It takes a ``Request`` as an input and should return a ``Response`` as an
output. Using this interface makes your code compatible with all frameworks
using the Symfony2 components. And this will gives you many cool features for
free.

Creating a framework based on the Symfony2 components is really easy. Here is
a very simple, but fully-featured framework based on the Symfony2 components:

    $routes = new RouteCollection();
    $routes->add('hello', new Route('/hello', array('_controller' =>
        function (Request $request) {
            return new Response(sprintf("Hello %s", $request->get('name')));
        }
    )));

    $request = Request::createFromGlobals();

    $context = new RequestContext();
    $context->fromRequest($request);

    $matcher = new UrlMatcher($routes, $context);

    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new RouterListener($matcher));

    $resolver = new ControllerResolver();

    $kernel = new HttpKernel($dispatcher, $resolver);

    $kernel->handle($request)->send();

This is all you need to create a flexible framework with the Symfony2
components.

Want to add an HTTP reverse proxy and benefit from HTTP caching and Edge Side
Includes?

    $kernel = new HttpKernel($dispatcher, $resolver);

    $kernel = new HttpCache($kernel, new Store(__DIR__.'/cache'));

Want to functional test this small framework?

    $client = new Client($kernel);
    $crawler = $client->request('GET', '/hello/Fabien');

    $this->assertEquals('Fabien', $crawler->filter('p > span')->text());

Want nice error pages instead of ugly PHP exceptions?

    $dispatcher->addSubscriber(new ExceptionListener(function (Request $request) {
        $msg = 'Something went wrong! ('.$request->get('exception')->getMessage().')';

        return new Response($msg, 500);
    }));

And that's why the simple looking ``HttpKernelInterface`` is so powerful. It
gives you access to a lot of cool features, ready to be used out of the box,
with no efforts.

Resources
---------

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Component/HttpKernel/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export SYMFONY_EVENT_DISPATCHER=../path/to/EventDispatcher
    export SYMFONY_HTTP_FOUNDATION=../path/to/HttpFoundation
    export SYMFONY_DEPENDENCY_INJECTION=../path/to/DependencyInjection
    export SYMFONY_CONSOLE=../path/to/Console
    export SYMFONY_BROWSER_KIT=../path/to/BrowserKit
    export SYMFONY_FINDER=../path/to/Finder
    export SYMFONY_PROCESS=../path/to/Process
    export SYMFONY_ROUTING=../path/to/Routing
    export SYMFONY_CONFIG=../path/to/Config
