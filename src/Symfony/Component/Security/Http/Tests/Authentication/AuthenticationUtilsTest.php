<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authentication;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthenticationUtilsTest extends TestCase
{
    public function testLastAuthenticationErrorWhenRequestHasAttribute()
    {
        $request = Request::create('/');
        $request->attributes->set(Security::AUTHENTICATION_ERROR, 'my error');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('my error', $utils->getLastAuthenticationError());
    }

    public function testLastAuthenticationErrorInSession()
    {
        $request = Request::create('/');

        $session = new Session(new MockArraySessionStorage());
        $session->set(Security::AUTHENTICATION_ERROR, 'session error');
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('session error', $utils->getLastAuthenticationError());
        $this->assertFalse($session->has(Security::AUTHENTICATION_ERROR));
    }

    public function testLastAuthenticationErrorInSessionWithoutClearing()
    {
        $request = Request::create('/');

        $session = new Session(new MockArraySessionStorage());
        $session->set(Security::AUTHENTICATION_ERROR, 'session error');
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('session error', $utils->getLastAuthenticationError(false));
        $this->assertTrue($session->has(Security::AUTHENTICATION_ERROR));
    }

    public function testLastUserNameIsDefinedButNull()
    {
        $request = Request::create('/');
        $request->attributes->set(Security::LAST_USERNAME, null);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('', $utils->getLastUsername());
    }

    public function testLastUserNameIsDefined()
    {
        $request = Request::create('/');
        $request->attributes->set(Security::LAST_USERNAME, 'user');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('user', $utils->getLastUsername());
    }

    public function testLastUserNameIsDefinedInSessionButNull()
    {
        $request = Request::create('/');

        $session = new Session(new MockArraySessionStorage());
        $session->set(Security::LAST_USERNAME, null);
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('', $utils->getLastUsername());
    }

    public function testLastUserNameIsDefinedInSession()
    {
        $request = Request::create('/');

        $session = new Session(new MockArraySessionStorage());
        $session->set(Security::LAST_USERNAME, 'user');
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('user', $utils->getLastUsername());
    }
}
