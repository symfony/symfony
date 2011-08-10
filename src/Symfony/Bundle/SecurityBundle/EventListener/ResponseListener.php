<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\SecurityBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * Adds remember-me cookies to the Response.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ResponseListener
{
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($request->attributes->has(RememberMeServicesInterface::COOKIE_ATTR_NAME)) {
            $response->headers->setCookie($request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME));
        }
    }
}
