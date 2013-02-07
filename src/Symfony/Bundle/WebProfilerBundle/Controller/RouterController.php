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

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;

/**
 * RouterController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterController extends ContainerAware
{
    /**
     * Renders the profiler panel for the given token.
     *
     * @param string $token The profiler token
     *
     * @return Response A Response instance
     */
    public function panelAction($token)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        if (!$this->container->has('router')) {
            return new Response('The Router is not enabled.');
        }
        $router = $this->container->get('router');

        $profile = $profiler->loadProfile($token);

        $context = $router->getContext();
        $context->setMethod($profile->getMethod());
        $matcher = new TraceableUrlMatcher($router->getRouteCollection(), $context);

        $request = $profile->getCollector('request');

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Router:panel.html.twig', array(
            'request' => $request,
            'router'  => $profile->getCollector('router'),
            'traces'  => $matcher->getTraces($request->getPathInfo()),
        ));
    }
}
