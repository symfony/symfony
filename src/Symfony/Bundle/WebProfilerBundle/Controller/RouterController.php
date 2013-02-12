<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * RouterController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterController
{
    private $profiler;
    private $twig;
    private $router;
    private $routes;

    public function __construct(Profiler $profiler, \Twig_Environment $twig, RouterInterface $router = null, RouteCollection $routes = null)
    {
        $this->profiler = $profiler;
        $this->twig = $twig;
        $this->router = $router;
        $this->routes = $routes;

        if (null === $this->routes && null !== $this->router) {
            $this->routes = $this->router->getRouteCollection();
        }
    }

    /**
     * Renders the profiler panel for the given token.
     *
     * @param string $token The profiler token
     *
     * @return Response A Response instance
     */
    public function panelAction($token)
    {
        $this->profiler->disable();

        if (null === $this->matcher || null === $this->routes) {
            return new Response('The Router is not enabled.');
        }

        $profile = $this->profiler->loadProfile($token);

        $matcher = new TraceableUrlMatcher($this->routes);
        $request = $profile->getCollector('request');

        return new Response($this->twig->render('@WebProfiler/Router/panel.html.twig', array(
            'request' => $request,
            'router'  => $profile->getCollector('router'),
            'traces'  => $matcher->getTraces($request),
        )));
    }
}
