<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for custom authentication failure handlers.
 *
 * If you want to customize the failure handling process, instead of
 * overwriting the respective listener globally, you can set a custom failure
 * handler which implements this interface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface AuthenticationFailureHandlerInterface
{
    /**
     * This is called when an interactive authentication attempt fails. This is
     * called by authentication listeners inheriting from
     * AbstractAuthenticationListener.
     *
     * @param Request                 $request
     * @param AuthenticationException $exception
     *
     * @return Response the response to return
     */
    function onAuthenticationFailure(Request $request, AuthenticationException $exception);
}
