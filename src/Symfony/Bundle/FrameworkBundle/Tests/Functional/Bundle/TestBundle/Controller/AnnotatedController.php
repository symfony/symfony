<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AnnotatedController
{
    #[Route('/null_request', name: 'null_request')]
    public function requestDefaultNullAction(Request $request = null): Response
    {
        return new Response($request ? $request::class : null);
    }

    #[Route('/null_argument', name: 'null_argument')]
    public function argumentDefaultNullWithoutRouteParamAction($value = null): Response
    {
        return new Response($value);
    }

    #[Route('/null_argument_with_route_param/{value}', name: 'null_argument_with_route_param')]
    public function argumentDefaultNullWithRouteParamAction($value = null): Response
    {
        return new Response($value);
    }

    #[Route('/argument_with_route_param_and_default/{value}', defaults: ['value' => 'value'], name: 'argument_with_route_param_and_default')]
    public function argumentWithoutDefaultWithRouteParamAndDefaultAction($value): Response
    {
        return new Response($value);
    }

    #[Route('/create-transaction')]
    public function createTransaction(): Response
    {
        return new Response();
    }
}
