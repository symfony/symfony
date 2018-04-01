<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\WebProfilerBundle\Controller;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Routing\Matcher\UrlMatcherInterface;
use Symphony\Component\Routing\Matcher\TraceableUrlMatcher;
use Symphony\Component\Routing\RouteCollection;
use Symphony\Component\Routing\RouterInterface;
use Symphony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symphony\Component\HttpKernel\Profiler\Profiler;
use Symphony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Twig\Environment;

/**
 * RouterController.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class RouterController
{
    private $profiler;
    private $twig;
    private $matcher;
    private $routes;

    public function __construct(Profiler $profiler = null, Environment $twig, UrlMatcherInterface $matcher = null, RouteCollection $routes = null)
    {
        $this->profiler = $profiler;
        $this->twig = $twig;
        $this->matcher = $matcher;
        $this->routes = (null === $routes && $matcher instanceof RouterInterface) ? $matcher->getRouteCollection() : $routes;
    }

    /**
     * Renders the profiler panel for the given token.
     *
     * @param string $token The profiler token
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function panelAction($token)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        if (null === $this->matcher || null === $this->routes) {
            return new Response('The Router is not enabled.', 200, array('Content-Type' => 'text/html'));
        }

        $profile = $this->profiler->loadProfile($token);

        /** @var RequestDataCollector $request */
        $request = $profile->getCollector('request');

        return new Response($this->twig->render('@WebProfiler/Router/panel.html.twig', array(
            'request' => $request,
            'router' => $profile->getCollector('router'),
            'traces' => $this->getTraces($request, $profile->getMethod()),
        )), 200, array('Content-Type' => 'text/html'));
    }

    /**
     * Returns the routing traces associated to the given request.
     */
    private function getTraces(RequestDataCollector $request, string $method): array
    {
        $traceRequest = Request::create(
            $request->getPathInfo(),
            $request->getRequestServer(true)->get('REQUEST_METHOD'),
            array(),
            $request->getRequestCookies(true)->all(),
            array(),
            $request->getRequestServer(true)->all()
        );

        $context = $this->matcher->getContext();
        $context->setMethod($method);
        $matcher = new TraceableUrlMatcher($this->routes, $context);

        return $matcher->getTracesForRequest($traceRequest);
    }
}
