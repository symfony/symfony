<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Logout;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\TokenStorage\ClearableTokenStorageInterface;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class CsrfTokenClearingLogoutHandler implements LogoutHandlerInterface
{
    private $csrfTokenStorage;

    public function __construct(ClearableTokenStorageInterface $csrfTokenStorage)
    {
        $this->csrfTokenStorage = $csrfTokenStorage;
    }

    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        $this->csrfTokenStorage->clear();
    }
}
