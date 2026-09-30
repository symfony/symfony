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
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class AuthenticationUtilsTest extends TestCase
{
    public function testLastAuthenticationErrorWhenRequestHasAttribute()
    {
        $authenticationError = new AuthenticationException();
        $request = Request::create('/');
        $request->attributes->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $authenticationError);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame($authenticationError, $utils->getLastAuthenticationError());
    }

    public function testLastAuthenticationErrorInSession()
    {
        $authenticationError = new AuthenticationException();

        $request = Request::create('/');

        $session = new Session(new MockArraySessionStorage());
        $session->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $authenticationError);
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame($authenticationError, $utils->getLastAuthenticationError());
        $this->assertFalse($session->has(SecurityRequestAttributes::AUTHENTICATION_ERROR));
    }

    public function testLastAuthenticationErrorInSessionWithoutClearing()
    {
        $authenticationError = new AuthenticationException();

        $request = Request::create('/');

        $session = new Session(new MockArraySessionStorage());
        $session->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $authenticationError);
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame($authenticationError, $utils->getLastAuthenticationError(false));
        $this->assertTrue($session->has(SecurityRequestAttributes::AUTHENTICATION_ERROR));
    }

    public function testLastUserNameIsDefinedButNull()
    {
        $request = Request::create('/');
        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, null);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('', $utils->getLastUsername());
    }

    public function testLastUserNameIsDefined()
    {
        $request = Request::create('/');
        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'user');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('user', $utils->getLastUsername());
    }

    public function testLastUserNameIsDefinedInSessionButNull()
    {
        $request = Request::create('/');

        $session = new Session(new MockArraySessionStorage());
        $session->set(SecurityRequestAttributes::LAST_USERNAME, null);
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
        $session->set(SecurityRequestAttributes::LAST_USERNAME, 'user');
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('user', $utils->getLastUsername());
    }

    public function testReAuthenticationAttributeWhenRequestHasAttribute()
    {
        $request = Request::create('/login');
        $request->attributes->set(SecurityRequestAttributes::RE_AUTHENTICATION_ATTRIBUTE, 'IS_AUTHENTICATED_RECENTLY');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('IS_AUTHENTICATED_RECENTLY', $utils->getReAuthenticationAttribute());
    }

    public function testReAuthenticationAttributeInSession()
    {
        $request = Request::create('/login');

        $session = new Session(new MockArraySessionStorage());
        $session->set(SecurityRequestAttributes::RE_AUTHENTICATION_ATTRIBUTE, 'IS_AUTHENTICATED_VERY_RECENTLY');
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        // a mistyped password sends the user back to the same page, which still has to know
        // what it is asking for; only a successful login ends the re-authentication
        $utils = new AuthenticationUtils($requestStack);
        $this->assertSame('IS_AUTHENTICATED_VERY_RECENTLY', $utils->getReAuthenticationAttribute());
        $this->assertSame('IS_AUTHENTICATED_VERY_RECENTLY', $utils->getReAuthenticationAttribute());
        $this->assertTrue($session->has(SecurityRequestAttributes::RE_AUTHENTICATION_ATTRIBUTE));
    }

    public function testNoReAuthenticationAttribute()
    {
        $request = Request::create('/login');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $utils = new AuthenticationUtils($requestStack);
        $this->assertNull($utils->getReAuthenticationAttribute());
    }
}
