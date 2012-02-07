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
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Bundle\FrameworkBundle\Routing\RedirectableUrlMatcher;
use Symfony\Component\Routing\Matcher\ArrayLogger;

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

        $traces = array();
        $profile = $profiler->loadProfile($token);
        $pathinfo = $profile->getCollector('request')->getPathInfo();
        $router = $this->container->get('router');
        $matcher = new RedirectableUrlMatcher($router->getRouteCollection(), $router->getContext());
        $matcher->setLogger($logger = new ArrayLogger());
        try {
            $matcher->match($pathinfo);
        } catch (\Exception $e) {
        }
        $traces = $logger->getTraces();

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Router:panel.html.twig', array(
            'pathinfo' => $pathinfo,
            'traces'   => $traces,
        ));
    }
}
