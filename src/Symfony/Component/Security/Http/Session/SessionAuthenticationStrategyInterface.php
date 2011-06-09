<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\Security\Http\Session;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * SessionAuthenticationStrategyInterface
 *
 * Implementation are responsible for updating the session after an interactive
 * authentication attempt was successful.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface SessionAuthenticationStrategyInterface
{
    /**
     * This performs any necessary changes to the session.
     *
     * This method is called before the SecurityContext is populated with a
     * Token, and only by classes inheriting from AbstractAuthenticationListener.
     *
     * @param Request        $request
     * @param TokenInterface $token
     *
     * @return void
     */
    function onAuthentication(Request $request, TokenInterface $token);
}
