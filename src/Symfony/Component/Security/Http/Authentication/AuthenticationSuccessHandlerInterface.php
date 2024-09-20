<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Interface for a custom authentication success handler.
 *
 * If you want to customize the success handling process, instead of
 * overwriting the respective listener globally, you can set a custom success
 * handler which implements this interface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface AuthenticationSuccessHandlerInterface
{
    /**
     * Usually called by AuthenticatorInterface::onAuthenticationSuccess() implementations.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response;
}
