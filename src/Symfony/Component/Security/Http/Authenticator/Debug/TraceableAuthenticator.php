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
    private AuthenticatorInterface $authenticator;
    private ?Passport $passport = null;
    private ?float $duration = null;
    private ClassStub|string $stub;

    public function __construct(AuthenticatorInterface $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    public function getInfo(): array
    {
        return [
            'supports' => true,
            'passport' => $this->passport,
            'duration' => $this->duration,
            'stub' => $this->stub ??= class_exists(ClassStub::class) ? new ClassStub(\get_class($this->authenticator)) : \get_class($this->authenticator),
        ];
    }

    public function supports(Request $request): ?bool
    {
        return $this->authenticator->supports($request);
    }

    public function authenticate(Request $request): Passport
    {
        $startTime = microtime(true);
        $this->passport = $this->authenticator->authenticate($request);
        $this->duration = microtime(true) - $startTime;

        return $this->passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return $this->authenticator->createToken($passport, $firewallName);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->authenticator->onAuthenticationSuccess($request, $token, $firewallName);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->authenticator->onAuthenticationFailure($request, $exception);
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
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

    /**
     * @internal
     */
    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator;
    }

    public function __call($method, $args)
    {
        return $this->authenticator->{$method}(...$args);
    }
}
