<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\SessionValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class SessionValueResolverTest extends TestCase
{
    public function testResolveReturnsSession()
    {
        $resolver = new SessionValueResolver();
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $metadata = new ArgumentMetadata('session', SessionInterface::class, false, false, null);

        $this->assertSame([$session], $resolver->resolve($request, $metadata));
    }

    public function testResolveSkipsWhenNoSession()
    {
        $resolver = new SessionValueResolver();
        $request = Request::create('/');

        $metadata = new ArgumentMetadata('session', SessionInterface::class, false, false, null);

        $this->assertSame([], $resolver->resolve($request, $metadata));
    }

    public function testResolveSkipsUnrelatedTypes()
    {
        $resolver = new SessionValueResolver();
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $metadata = new ArgumentMetadata('foo', \stdClass::class, false, false, null);

        $this->assertSame([], $resolver->resolve($request, $metadata));
    }

    public function testResolveDefersWhenMatchingNamedAttributeExists()
    {
        $resolver = new SessionValueResolver();
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $request->attributes->set('session', new Session(new MockArraySessionStorage()));

        $metadata = new ArgumentMetadata('session', SessionInterface::class, false, false, null);

        $this->assertSame([], $resolver->resolve($request, $metadata));
    }
}
