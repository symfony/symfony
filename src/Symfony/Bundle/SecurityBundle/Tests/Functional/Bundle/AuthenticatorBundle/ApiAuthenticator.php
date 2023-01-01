<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AuthenticatorBundle;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiAuthenticator extends AbstractAuthenticator
{
    private $selfLoadingUser = false;

    public function __construct(bool $selfLoadingUser = false)
    {
        $this->selfLoadingUser = $selfLoadingUser;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-USER-EMAIL');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->headers->get('X-USER-EMAIL');
        if (!str_contains($email, '@')) {
            throw new BadCredentialsException('Email is not a valid email address.');
        }

        $userLoader = null;
        if ($this->selfLoadingUser) {
            $userLoader = fn ($username) => new InMemoryUser($username, 'test', ['ROLE_USER']);
        }

        return new SelfValidatingPassport(new UserBadge($email, $userLoader));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => $exception->getMessageKey(),
        ], JsonResponse::HTTP_FORBIDDEN);
    }
}
