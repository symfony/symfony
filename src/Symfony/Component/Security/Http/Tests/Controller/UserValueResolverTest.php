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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Controller\UserValueResolver;

class UserValueResolverTest extends TestCase
{
    public function testResolveNoToken()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null);

        self::assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolveNoUser()
    {
        $mock = self::createMock(UserInterface::class);
        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password'), 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', \get_class($mock), false, false, null);

        self::assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolveWrongType()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', null, false, false, null);

        self::assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolve()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null);

        self::assertTrue($resolver->supports(Request::create('/'), $metadata));
        self::assertSame([$user], iterator_to_array($resolver->resolve(Request::create('/'), $metadata)));
    }

    public function testResolveWithAttribute()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = self::createMock(ArgumentMetadata::class);
        $metadata = new ArgumentMetadata('foo', null, false, false, null, false, [new CurrentUser()]);

        self::assertTrue($resolver->supports(Request::create('/'), $metadata));
        self::assertSame([$user], iterator_to_array($resolver->resolve(Request::create('/'), $metadata)));
    }

    public function testResolveWithAttributeAndNoUser()
    {
        $tokenStorage = new TokenStorage();

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', null, false, false, null, false, [new CurrentUser()]);

        self::assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    public function testIntegration()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $argumentResolver = new ArgumentResolver(null, [new UserValueResolver($tokenStorage)]);
        self::assertSame([$user], $argumentResolver->getArguments(Request::create('/'), function (UserInterface $user) {}));
    }

    public function testIntegrationNoUser()
    {
        $tokenStorage = new TokenStorage();

        $argumentResolver = new ArgumentResolver(null, [new UserValueResolver($tokenStorage), new DefaultValueResolver()]);
        self::assertSame([null], $argumentResolver->getArguments(Request::create('/'), function (UserInterface $user = null) {}));
    }
}
