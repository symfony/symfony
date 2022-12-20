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
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
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
        $this->request = Request::create('/login');
        $this->request->request->set('_remember_me', true);
        $this->response = new Response();
    }

    public function testSuccessfulLoginWithoutSupportingAuthenticator()
    {
        $passport = $this->createPassport([]);

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        self::assertFalse($passport->hasBadge(RememberMeBadge::class));
    }

    public function testSuccessfulLoginWithoutRequestParameter()
    {
        $this->request = Request::create('/login');
        $passport = $this->createPassport();

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        self::assertFalse($passport->getBadge(RememberMeBadge::class)->isEnabled());
    }

    public function testSuccessfulLoginWhenRememberMeAlwaysIsTrue()
    {
        $passport = $this->createPassport();
        $listener = new CheckRememberMeConditionsListener(['always_remember_me' => true]);

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        self::assertTrue($passport->getBadge(RememberMeBadge::class)->isEnabled());
    }

    /**
     * @dataProvider provideRememberMeOptInValues
     */
    public function testSuccessfulLoginWithOptInRequestParameter($optInValue)
    {
        $this->request->request->set('_remember_me', $optInValue);
        $passport = $this->createPassport();

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        self::assertTrue($passport->getBadge(RememberMeBadge::class)->isEnabled());
    }

    public function provideRememberMeOptInValues()
    {
        yield ['true'];
        yield ['1'];
        yield ['on'];
        yield ['yes'];
        yield [true];
    }

    private function createLoginSuccessfulEvent(PassportInterface $passport)
    {
        return new LoginSuccessEvent(self::createMock(AuthenticatorInterface::class), $passport, self::createMock(TokenInterface::class), $this->request, $this->response, 'main_firewall');
    }

    private function createPassport(array $badges = null)
    {
        return new SelfValidatingPassport(new UserBadge('test', function ($username) { return new User($username, null); }), $badges ?? [new RememberMeBadge()]);
    }
}
