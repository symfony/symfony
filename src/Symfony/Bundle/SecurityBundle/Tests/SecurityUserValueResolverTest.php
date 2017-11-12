<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityUserValueResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityUserValueResolverTest extends TestCase
{
    public function testResolveNoToken(): void
    {
        $tokenStorage = new TokenStorage();
        $resolver = new SecurityUserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null);

        $this->assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolveNoUser(): void
    {
        $mock = $this->getMockBuilder(UserInterface::class)->getMock();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new SecurityUserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', get_class($mock), false, false, null);

        $this->assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolveWrongType(): void
    {
        $tokenStorage = new TokenStorage();
        $resolver = new SecurityUserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', null, false, false, null);

        $this->assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolve(): void
    {
        $user = $this->getMockBuilder(UserInterface::class)->getMock();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->expects($this->any())->method('getUser')->willReturn($user);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new SecurityUserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null);

        $this->assertTrue($resolver->supports(Request::create('/'), $metadata));
        $this->assertSame(array($user), iterator_to_array($resolver->resolve(Request::create('/'), $metadata)));
    }

    public function testIntegration(): void
    {
        $user = $this->getMockBuilder(UserInterface::class)->getMock();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->expects($this->any())->method('getUser')->willReturn($user);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $argumentResolver = new ArgumentResolver(null, array(new SecurityUserValueResolver($tokenStorage)));
        $this->assertSame(array($user), $argumentResolver->getArguments(Request::create('/'), function (UserInterface $user): void {}));
    }

    public function testIntegrationNoUser(): void
    {
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $argumentResolver = new ArgumentResolver(null, array(new SecurityUserValueResolver($tokenStorage), new DefaultValueResolver()));
        $this->assertSame(array(null), $argumentResolver->getArguments(Request::create('/'), function (UserInterface $user = null): void {}));
    }
}

abstract class DummyUser implements UserInterface
{
}

abstract class DummySubUser extends DummyUser
{
}
