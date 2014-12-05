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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * RouterController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterController
{
    private $profiler;
    private $twig;
    private $matcher;
    private $routes;
    private $container;

    public function __construct(Profiler $profiler = null, \Twig_Environment $twig, UrlMatcherInterface $matcher = null, ContainerInterface $container,
                                RouteCollection $routes = null)
    {
        $this->profiler = $profiler;
        $this->twig = $twig;
        $this->matcher = $matcher;
        $this->routes = $routes;
        $this->container = $container;

        if (null === $this->routes && $this->matcher instanceof RouterInterface) {
            $this->routes = $matcher->getRouteCollection();
        }
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

        $context = $this->matcher->getContext();
        $context->setMethod($profile->getMethod());
        $matcher = new TraceableUrlMatcher($this->routes, $context, $this->container);

        $request = $profile->getCollector('request');

        return new Response($this->twig->render('@WebProfiler/Router/panel.html.twig', array(
            'request' => $request,
            'router' => $profile->getCollector('router'),
            'traces' => $matcher->getTraces($request->getPathInfo()),
        )), 200, array('Content-Type' => 'text/html'));
    }
}
