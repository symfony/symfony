<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Session;

use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\HttpFoundation\Request;

/**
 * SessionAuthenticationStrategyInterface.
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
     * This method is called before the TokenStorage is populated with a
     * Token, and only by classes inheriting from AbstractAuthenticationListener.
     */
    public function onAuthentication(Request $request, TokenInterface $token);
}
