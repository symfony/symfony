HttpFoundation Component
========================

The Symfony2 HttpFoundation component adds an object-oriented layer on top of PHP for
everything related to the Web: Requests, Responses, Uploaded files, Cookies, Sessions, ...

In this example, we get a Request object from the current PHP global variables:

```
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$request = Request::createFromGlobals();
echo $request->getPathInfo();
```

You can also create a Request directly -- that's interesting for unit testing:

```
$request = Request::create('/?foo=bar', 'GET');
echo $request->getPathInfo();
```

And here is how to create and send a Response:

```
$response = new Response('Not Found', 404, array('Content-Type' => 'text/plain'));
$response->send();
```

The Request and the Response classes have many other methods that implement the HTTP specification.

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/HttpFoundation
