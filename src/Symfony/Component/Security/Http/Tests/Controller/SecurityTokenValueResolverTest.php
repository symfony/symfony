<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Controller\SecurityTokenValueResolver;

class SecurityTokenValueResolverTest extends TestCase
{
    public function testResolveSucceedsWithTokenInterface()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new SecurityTokenValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', TokenInterface::class, false, false, null);

        $this->assertSame([$token], $resolver->resolve(Request::create('/'), $metadata));
    }

    public function testResolveSucceedsWithSubclassType()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new SecurityTokenValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UsernamePasswordToken::class, false, false, null, false);

        $this->assertSame([$token], $resolver->resolve(Request::create('/'), $metadata));
    }

    public function testResolveSucceedsWithNullableParamAndNoToken()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new SecurityTokenValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', TokenInterface::class, false, false, null, true);

        $this->assertSame([], $resolver->resolve(Request::create('/'), $metadata));
    }

    public function testResolveThrowsUnauthenticatedWithNoToken()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new SecurityTokenValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UsernamePasswordToken::class, false, false, null, false);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('A security token is required but the token storage is empty.');

        $resolver->resolve(Request::create('/'), $metadata);
    }

    public function testIntegration()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $argumentResolver = new ArgumentResolver(null, [new SecurityTokenValueResolver($tokenStorage)]);
        $this->assertSame([$token], $argumentResolver->getArguments(Request::create('/'), static function (TokenInterface $token) {}));
    }

    public function testIntegrationNoToken()
    {
        $tokenStorage = new TokenStorage();

        $argumentResolver = new ArgumentResolver(null, [new SecurityTokenValueResolver($tokenStorage), new DefaultValueResolver()]);
        $this->assertSame([null], $argumentResolver->getArguments(Request::create('/'), static function (?TokenInterface $token) {}));
    }

    public function testIntegrationNonNullablwWithNoToken()
    {
        $tokenStorage = new TokenStorage();

        $argumentResolver = new ArgumentResolver(null, [new SecurityTokenValueResolver($tokenStorage), new DefaultValueResolver()]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('A security token is required but the token storage is empty.');

        $argumentResolver->getArguments(Request::create('/'), static function (TokenInterface $token) {});
    }
}
