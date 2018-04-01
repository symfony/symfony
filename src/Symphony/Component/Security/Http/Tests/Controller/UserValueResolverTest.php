<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\Controller\ArgumentResolver;
use Symphony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symphony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\User\UserInterface;
use Symphony\Component\Security\Http\Controller\UserValueResolver;

class UserValueResolverTest extends TestCase
{
    public function testResolveNoToken()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null);

        $this->assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolveNoUser()
    {
        $mock = $this->getMockBuilder(UserInterface::class)->getMock();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', get_class($mock), false, false, null);

        $this->assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolveWrongType()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new UserValueResolver($tokenStorage);
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

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null);

        $this->assertTrue($resolver->supports(Request::create('/'), $metadata));
        $this->assertSame(array($user), iterator_to_array($resolver->resolve(Request::create('/'), $metadata)));
    }

    public function testIntegration()
    {
        $user = $this->getMockBuilder(UserInterface::class)->getMock();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->expects($this->any())->method('getUser')->willReturn($user);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $argumentResolver = new ArgumentResolver(null, array(new UserValueResolver($tokenStorage)));
        $this->assertSame(array($user), $argumentResolver->getArguments(Request::create('/'), function (UserInterface $user) {}));
    }

    public function testIntegrationNoUser()
    {
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $argumentResolver = new ArgumentResolver(null, array(new UserValueResolver($tokenStorage), new DefaultValueResolver()));
        $this->assertSame(array(null), $argumentResolver->getArguments(Request::create('/'), function (UserInterface $user = null) {}));
    }
}

abstract class DummyUser implements UserInterface
{
}

abstract class DummySubUser extends DummyUser
{
}
