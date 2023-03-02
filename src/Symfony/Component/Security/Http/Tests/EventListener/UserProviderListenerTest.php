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
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\UserProviderListener;

class UserProviderListenerTest extends TestCase
{
    private $userProvider;
    private $listener;

    protected function setUp(): void
    {
        $this->userProvider = new InMemoryUserProvider();
        $this->listener = new UserProviderListener($this->userProvider);
    }

    public function testSetUserProvider()
    {
        $passport = new SelfValidatingPassport(new UserBadge('wouter'));

        $this->listener->checkPassport(new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport));

        $user = new InMemoryUser('wouter', null);
        $this->userProvider->createUser($user);
        $this->assertTrue($user->isEqualTo($passport->getUser()));
    }

    /**
     * @dataProvider provideCompletePassports
     */
    public function testNotOverrideUserLoader($passport)
    {
        $badgeBefore = $passport->hasBadge(UserBadge::class) ? $passport->getBadge(UserBadge::class) : null;
        $this->listener->checkPassport(new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport));

        $this->assertEquals($passport->hasBadge(UserBadge::class) ? $passport->getBadge(UserBadge::class) : null, $badgeBefore);
    }

    public static function provideCompletePassports()
    {
        yield [new SelfValidatingPassport(new UserBadge('wouter', function () {}))];
    }
}
