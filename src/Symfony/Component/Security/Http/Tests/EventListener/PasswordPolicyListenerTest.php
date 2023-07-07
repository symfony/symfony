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
use Symfony\Component\Security\Core\Exception\PasswordPolicyException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Policy\PasswordPolicyInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\PasswordPolicyListener;

class PasswordPolicyListenerTest extends TestCase
{
    /**
     * @param array<PasswordPolicyInterface> $policies
     *
     * @dataProvider providePassport
     */
    public function testPasswordIsNotAcceptable(Passport $passport, array $policies, string $expectedMessage)
    {
        // Given
        $event = new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport);
        $listener = new PasswordPolicyListener($policies);

        try {
            // When
            $listener->checkPassport($event);
            $this->fail('Expected exception to be thrown');
        } catch (PasswordPolicyException $e) {
            // Then
            $this->assertSame($expectedMessage, $e->getMessageKey());
        }
    }

    public static function providePassport(): iterable
    {
        yield [
            new Passport(
                new UserBadge('test', fn () => new InMemoryUser('test', 'qwerty')),
                new PasswordCredentials('qwerty')
            ),
            [new class() implements PasswordPolicyInterface {
                public function verify(string $plaintextPassword): bool
                {
                    return false;
                }
            }],
            'The password does not fulfill the password policy.',
        ];
    }
}
