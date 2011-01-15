<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector as BaseRequestDataCollector;

/**
 * RequestDataCollector.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RequestDataCollector extends BaseRequestDataCollector
{
    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        parent::collect($request, $response, $exception);

        $this->data['route'] = $request->attributes->get('_route');
    }

    /**
     * Gets the route.
     *
     * @return string The route
     */
    public function getRoute()
    {
        return $this->data['route'];
    }
}
