<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Session;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

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
     * This attribute name can be used by the implementation if it needs to
     * clear the CSRF token storage when there is no actual Response, yet.
     */
    public const CLEAR_CSRF_STORAGE_ATTR_NAME = '_security_clear_csrf_storage';

    /**
     * This performs any necessary changes to the session.
     *
     * This method should be called before the TokenStorage is populated with a
     * Token. It should be used by authentication listeners when a session is used.
     */
    public function onAuthentication(Request $request, TokenInterface $token);
}
