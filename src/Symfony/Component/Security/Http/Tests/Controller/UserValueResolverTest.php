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
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Controller\UserValueResolver;

class UserValueResolverTest extends TestCase
{
    /**
     * In Symfony 7, keep this test case but remove the call to supports().
     *
     * @group legacy
     */
    public function testSupportsFailsWithNoType()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', null, false, false, null);

        $this->assertSame([], $resolver->resolve(Request::create('/'), $metadata));
        $this->assertFalse($resolver->supports(Request::create('/'), $metadata));
    }

    /**
     * In Symfony 7, keep this test case but remove the call to supports().
     *
     * @group legacy
     */
    public function testSupportsFailsWhenDefaultValAndNoUser()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, true, $default = new InMemoryUser('username', 'password'));

        $this->assertSame([$default], $resolver->resolve(Request::create('/'), $metadata));
        $this->assertTrue($resolver->supports(Request::create('/'), $metadata));
    }

    public function testResolveSucceedsWithUserInterface()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null);

        $this->assertSame([$user], $resolver->resolve(Request::create('/'), $metadata));
    }

    public function testResolveSucceedsWithSubclassType()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', InMemoryUser::class, false, false, null, false, [new CurrentUser()]);

        $this->assertSame([$user], $resolver->resolve(Request::create('/'), $metadata));
    }

    public function testResolveSucceedsWithNullableParamAndNoUser()
    {
        $token = new NullToken();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', InMemoryUser::class, false, false, null, true, [new CurrentUser()]);

        $this->assertSame([null], $resolver->resolve(Request::create('/'), $metadata));
    }

    public function testResolveSucceedsWithNullableAttribute()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = $this->createMock(ArgumentMetadata::class);
        $metadata = new ArgumentMetadata('foo', null, false, false, null, false, [new CurrentUser()]);

        $this->assertSame([$user], $resolver->resolve(Request::create('/'), $metadata));
    }

    public function testResolveSucceedsWithTypedAttribute()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = $this->createMock(ArgumentMetadata::class);
        $metadata = new ArgumentMetadata('foo', InMemoryUser::class, false, false, null, false, [new CurrentUser()]);

        $this->assertSame([$user], $resolver->resolve(Request::create('/'), $metadata));
    }

    public function testResolveThrowsAccessDeniedWithWrongUserClass()
    {
        $user = $this->createMock(UserInterface::class);
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', InMemoryUser::class, false, false, null, false, [new CurrentUser()]);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/^The logged-in user is an instance of "Mock_UserInterface[^"]+" but a user of type "Symfony\\\\Component\\\\Security\\\\Core\\\\User\\\\InMemoryUser" is expected.$/');
        $resolver->resolve(Request::create('/'), $metadata);
    }

    public function testResolveThrowsAccessDeniedWithAttributeAndNoUser()
    {
        $tokenStorage = new TokenStorage();

        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null, false, [new CurrentUser()]);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('There is no logged-in user to pass to $foo, make the argument nullable if you want to allow anonymous access to the action.');
        $resolver->resolve(Request::create('/'), $metadata);
    }

    public function testResolveThrowsAcessDeniedWithNoToken()
    {
        $tokenStorage = new TokenStorage();
        $resolver = new UserValueResolver($tokenStorage);
        $metadata = new ArgumentMetadata('foo', UserInterface::class, false, false, null);

        $this->expectException(AccessDeniedException::class);
        $resolver->resolve(Request::create('/'), $metadata);
    }

    public function testIntegration()
    {
        $user = new InMemoryUser('username', 'password');
        $token = new UsernamePasswordToken($user, 'provider');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $argumentResolver = new ArgumentResolver(null, [new UserValueResolver($tokenStorage)]);
        $this->assertSame([$user], $argumentResolver->getArguments(Request::create('/'), function (UserInterface $user) {}));
    }

    public function testIntegrationNoUser()
    {
        $tokenStorage = new TokenStorage();

        $argumentResolver = new ArgumentResolver(null, [new UserValueResolver($tokenStorage), new DefaultValueResolver()]);
        $this->assertSame([null], $argumentResolver->getArguments(Request::create('/'), function (UserInterface $user = null) {}));
    }
}
