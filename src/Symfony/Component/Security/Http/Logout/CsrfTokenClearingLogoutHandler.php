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
use Symfony\Component\Security\Http\EventListener\CsrfTokenClearingLogoutListener;

trigger_deprecation('symfony/security-http', '5.4', 'The "%s" class is deprecated, use "%s" instead.', CsrfTokenClearingLogoutHandler::class, CsrfTokenClearingLogoutListener::class);

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 *
 * @deprecated since Symfony 5.4, use {@link CsrfTokenClearingLogoutListener} instead
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
