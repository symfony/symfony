<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;
use Symfony\Component\Routing\RouterInterface;

/**
 * RouterDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterDataCollector extends DataCollector
{
    private $router;

    public function __construct(RouterInterface $router = null)
    {
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data['path_info'] = $request->getPathInfo();

        if (!$this->router) {
            $this->data['traces'] = array();
        } else {
            $matcher = new TraceableUrlMatcher($this->router->getRouteCollection(), $this->router->getContext());

            $this->data['traces'] = $matcher->getTraces($request->getPathInfo());
        }
    }

    public function getPathInfo()
    {
        return $this->data['path_info'];
    }

    public function getTraces()
    {
        return $this->data['traces'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'router';
    }
}
