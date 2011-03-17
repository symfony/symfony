<?php

namespace Symfony\Component\Security\Http\Authorization;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * This is used by the ExceptionListener to translate an AccessDeniedException
 * to a Response object.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface AccessDeniedHandlerInterface
{
    /**
     * Handles an access denied failure.
     *
     * @param ExceptionEvent    $event
     * @param Request               $request
     * @param AccessDeniedException $accessDeniedException
     *
     * @return Response may return null
     */
    function handle(ExceptionEvent $event, Request $request, AccessDeniedException $accessDeniedException);
}