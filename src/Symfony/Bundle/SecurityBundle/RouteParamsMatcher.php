<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

class RouteParamsMatcher implements RequestMatcherInterface
{
    private $parameters;

    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function matches(Request $request)
    {
        if ($routeParams = $request->attributes->get('_route_params')) {
            return !array_diff_assoc($this->parameters, $routeParams);
        }

        return false;
    }
}
