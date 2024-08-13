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
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

/**
 * NotFullFledgedHandler for considering NotFullFledged Login equal to Normal Login except if IS_AUTHENTICATED_FULLY is asked
 * If IS_AUTHENTICATED_FULLY is in access denied Exception Attrribute, user is redirect to
 * startAuthentication function in AuthenticationTrustResolver
 * Otherwise The original AccessDeniedException is passing to accessDeniedHandler or ErrorPage or Thrown.
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
class NotFullFledgedEqualNormalLoginHandler implements NotFullFledgedHandlerInterface
{
    public function handle(ExceptionEvent $event, AccessDeniedException $exception, AuthenticationTrustResolverInterface $trustResolver, ?TokenInterface $token, ?LoggerInterface $logger, callable $starAuthenticationCallback): bool
    {
        if (!$trustResolver->isAuthenticated($token)) {
            $this->reauthenticate($starAuthenticationCallback, $event, $token, $exception, $logger);
        }

        foreach ($exception->getAttributes() as $attribute) {
            if (\in_array($attribute, [AuthenticatedVoter::IS_AUTHENTICATED_FULLY])) {
                $this->reauthenticate($starAuthenticationCallback, $event, $token, $exception, $logger);

                return true;
            }
        }

        return false;
    }

    private function reauthenticate(callable $starAuthenticationCallback, ExceptionEvent $event, ?TokenInterface $token, AccessDeniedException $exception, ?LoggerInterface $logger): void
    {
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
    }
}
