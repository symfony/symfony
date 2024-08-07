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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

/**
 * NotFullFledgedHandler for considering NotFullFledged Login has to be redirect to login page if AccessDeniedException is thrown
 * When an AccessDeniedException is thrown and user is not full fledged logged, he is redirected to login page with
 * start function from authenticationEntryPoint
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
class NotFullFledgedRedirectToStartAuthenticationHandler implements NotFullFledgedHandlerInterface
{
    public function handle( ExceptionEvent $event, AccessDeniedException $exception, AuthenticationTrustResolverInterface $trustResolver, ?TokenInterface $token, ?LoggerInterface $logger, callable $starAuthenticationCallback): bool
    {
        if (!$trustResolver->isFullFledged($token)) {
            $logger?->debug('Access denied, the user is not fully authenticated; redirecting to authentication entry point.', ['exception' => $exception]);

            try {
                $insufficientAuthenticationException = new InsufficientAuthenticationException('Full authentication is required to access this resource.', 0, $exception);
                if (null !== $token) {
                    $insufficientAuthenticationException->setToken($token);
                }

                $event->setResponse($starAuthenticationCallback($event->getRequest(), $insufficientAuthenticationException));
            } catch (\Exception $e) {
                $event->setThrowable($e);
            }

            return true;
        }

        return false;
    }
}
