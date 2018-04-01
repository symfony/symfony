<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Routing\Annotation\Route;

class AnnotatedController
{
    /**
     * @Route("/null_request", name="null_request")
     */
    public function requestDefaultNullAction(Request $request = null)
    {
        return new Response($request ? get_class($request) : null);
    }

    /**
     * @Route("/null_argument", name="null_argument")
     */
    public function argumentDefaultNullWithoutRouteParamAction($value = null)
    {
        return new Response($value);
    }

    /**
     * @Route("/null_argument_with_route_param/{value}", name="null_argument_with_route_param")
     */
    public function argumentDefaultNullWithRouteParamAction($value = null)
    {
        return new Response($value);
    }

    /**
     * @Route("/argument_with_route_param_and_default/{value}", defaults={"value": "value"}, name="argument_with_route_param_and_default")
     */
    public function argumentWithoutDefaultWithRouteParamAndDefaultAction($value)
    {
        return new Response($value);
    }
}
