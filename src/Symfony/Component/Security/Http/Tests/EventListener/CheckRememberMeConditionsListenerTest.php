<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\EventListener\CheckRememberMeConditionsListener;

class CheckRememberMeConditionsListenerTest extends TestCase
{
    private $listener;
    private $request;
    private $response;

    protected function setUp(): void
    {
        $this->listener = new CheckRememberMeConditionsListener();
    }

    public function testSuccessfulHttpLoginWithoutSupportingAuthenticator()
    {
        $this->createHttpRequest();

        $passport = $this->createPassport([]);

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        $this->assertFalse($passport->hasBadge(RememberMeBadge::class));
    }

    public function testSuccessfulJsonLoginWithoutSupportingAuthenticator()
    {
        $this->createJsonRequest();

        $passport = $this->createPassport([]);
        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        $this->assertFalse($passport->hasBadge(RememberMeBadge::class));
    }

    public function testSuccessfulLoginWithoutRequestParameter()
    {
        $this->request = Request::create('/login');
        $passport = $this->createPassport([new RememberMeBadge()]);

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        $this->assertFalse($passport->getBadge(RememberMeBadge::class)->isEnabled());
    }

    public function testSuccessfulHttpLoginWhenRememberMeAlwaysIsTrue()
    {
        $this->createHttpRequest();

        $passport = $this->createPassport();

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        $this->assertTrue($passport->getBadge(RememberMeBadge::class)->isEnabled());
    }

    public function testSuccessfulJsonLoginWhenRememberMeAlwaysIsTrue()
    {
        $this->createJsonRequest();

        $passport = $this->createPassport();

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        $this->assertTrue($passport->getBadge(RememberMeBadge::class)->isEnabled());
    }

    /**
     * @dataProvider provideRememberMeOptInValues
     */
    public function testSuccessfulHttpLoginWithOptInRequestParameter($optInValue)
    {
        $this->createHttpRequest();

        $this->request->request->set('_remember_me', $optInValue);
        $passport = $this->createPassport();

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        $this->assertTrue($passport->getBadge(RememberMeBadge::class)->isEnabled());
    }

    /**
     * @dataProvider provideRememberMeOptInValues
     */
    public function testSuccessfulJsonLoginWithOptInRequestParameter($optInValue)
    {
        $this->createJsonRequest(['_remember_me' => $optInValue]);

        $passport = $this->createPassport([new RememberMeBadge(['_remember_me' => $optInValue])]);

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        $this->assertTrue($passport->getBadge(RememberMeBadge::class)->isEnabled());
    }

    public static function provideRememberMeOptInValues()
    {
        yield ['true'];
        yield ['1'];
        yield ['on'];
        yield ['yes'];
        yield [true];
    }

    private function createHttpRequest(): void
    {
        $this->request = Request::create('/login');
        $this->request->request->set('_remember_me', true);
        $this->response = new Response();
    }

    private function createJsonRequest(array $content = ['_remember_me' => true]): void
    {
        $this->request = Request::create('/login', 'POST', [], [], [], [], json_encode($content));
        $this->request->headers->add(['Content-Type' => 'application/json']);
        $this->response = new Response();
    }

    private function createLoginSuccessfulEvent(Passport $passport)
    {
        return new LoginSuccessEvent($this->createMock(AuthenticatorInterface::class), $passport, $this->createMock(TokenInterface::class), $this->request, $this->response, 'main_firewall');
    }

    private function createPassport(array $badges = null)
    {
        return new SelfValidatingPassport(new UserBadge('test', fn ($username) => new InMemoryUser($username, null)), $badges ?? [new RememberMeBadge(['_remember_me' => true])]);
    }
}
