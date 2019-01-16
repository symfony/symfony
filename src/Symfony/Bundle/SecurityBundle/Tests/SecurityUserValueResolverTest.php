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

/**
 * @group legacy
 */
class SecurityUserValueResolverTest extends TestCase
{
    public function testResolveNoToken()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new SecurityUserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null);

        $this->assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolveNoUser()
    {
        $mock = $this->getMockBuilder(UserInterface::class)->getMock();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new SecurityUserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', \get_class($mock), false, false, null);

        $this->assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolveWrongType()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new SecurityUserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', null, false, false, null);

        $this->assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolve()
    {
        $user = $this->getMockBuilder(UserInterface::class)->getMock();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->expects($this->any())->method('getUser')->willReturn($user);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new SecurityUserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null);

        $this->assertTrue($resolver->supports(Request::create('/'), $metadata));
        $this->assertSame([$user], iterator_to_array($resolver->resolve(Request::create('/'), $metadata)));
    }

    public function testIntegration()
    {
        $user = $this->getMockBuilder(UserInterface::class)->getMock();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->expects($this->any())->method('getUser')->willReturn($user);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $argumentResolver = new ArgumentResolver(null, [new SecurityUserValueResolver($tokenStorage)]);
        $this->assertSame([$user], $argumentResolver->getArguments(Request::create('/'), function (UserInterface $user) {}));
    }

    public function testIntegrationNoUser()
    {
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $argumentResolver = new ArgumentResolver(null, [new SecurityUserValueResolver($tokenStorage), new DefaultValueResolver()]);
        $this->assertSame([null], $argumentResolver->getArguments(Request::create('/'), function (UserInterface $user = null) {}));
    }
}

abstract class DummyUser implements UserInterface
{
}

abstract class DummySubUser extends DummyUser
{
}
