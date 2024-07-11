<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authorization;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * This is used by the ExceptionListener to translate an AccessDeniedException
 * to a Response object.
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
interface NotFullFledgedHandlerInterface
{
    /**
     * Handles a not full fledged case for acces denied failure.
     * @return null|Response
     *      null: throw original AcessDeniedException
     *      Response: you can return your own response, AccesDeniedException wil be ignored
     */
    public function handle(Request $request, AccessDeniedException $accessDeniedException, AuthenticationTrustResolverInterface $trustResolver, ?TokenInterface $token, callable $reauthenticateResponse): ?Response;
}
