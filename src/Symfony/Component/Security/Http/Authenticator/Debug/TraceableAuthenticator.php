<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Debug;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\EntryPoint\Exception\NotAnEntryPointException;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * Collects info about an authenticator for debugging purposes.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class TraceableAuthenticator implements AuthenticatorInterface, InteractiveAuthenticatorInterface, AuthenticationEntryPointInterface
{
    private ?bool $supports = false;
    private ?Passport $passport = null;
    private ?float $duration = null;
    private ClassStub|string $stub;
    private ?bool $authenticated = null;
    private ?AuthenticationException $exception = null;

    public function __construct(private AuthenticatorInterface $authenticator)
    {
    }

    public function getInfo(): array
    {
        return [
            'supports' => $this->supports,
            'passport' => $this->passport,
            'duration' => $this->duration,
            'stub' => $this->stub ??= class_exists(ClassStub::class) ? new ClassStub($this->authenticator::class) : $this->authenticator::class,
            'authenticated' => $this->authenticated,
            'badges' => array_map(
                static function (BadgeInterface $badge): array {
                    return [
                        'stub' => class_exists(ClassStub::class) ? new ClassStub($badge::class) : $badge::class,
                        'resolved' => $badge->isResolved(),
                    ];
                },
                $this->passport?->getBadges() ?? [],
            ),
            'exception' => $this->exception,
        ];
    }

    public function supports(Request $request): ?bool
    {
        return $this->supports = $this->authenticator->supports($request);
    }

    public function authenticate(Request $request): Passport
    {
        $startTime = microtime(true);
        try {
            $this->passport = $this->authenticator->authenticate($request);
        } finally {
            $this->duration = microtime(true) - $startTime;
        }

        return $this->passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return $this->authenticator->createToken($passport, $firewallName);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->authenticated = true;

        return $this->authenticator->onAuthenticationSuccess($request, $token, $firewallName);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->authenticated = false;
        $this->exception = $exception->getPrevious() instanceof AuthenticationException
            ? $exception->getPrevious()
            : $exception
        ;

        return $this->authenticator->onAuthenticationFailure($request, $exception);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (!$this->authenticator instanceof AuthenticationEntryPointInterface) {
            throw new NotAnEntryPointException();
        }

        return $this->authenticator->start($request, $authException);
    }

    public function isInteractive(): bool
    {
        return $this->authenticator instanceof InteractiveAuthenticatorInterface && $this->authenticator->isInteractive();
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator;
    }

    public function __call($method, $args): mixed
    {
        return $this->authenticator->{$method}(...$args);
    }
}
