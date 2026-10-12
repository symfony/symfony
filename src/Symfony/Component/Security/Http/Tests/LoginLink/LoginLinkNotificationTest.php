<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\LoginLink;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Security\Http\LoginLink\LoginLinkDetails;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;

class LoginLinkNotificationTest extends TestCase
{
    use ClockSensitiveTrait;

    #[TestWith([600, '10 minutes'])]
    #[TestWith([7200, '2 hours'])]
    public function testSmsMessageTellsWhenTheLinkExpires(int $lifetime, string $expiresIn)
    {
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify(\sprintf('+%d seconds', $lifetime))), 'Sign in');

        $message = $notification->asSmsMessage(new Recipient('', '+33612345678'));

        $this->assertSame(\sprintf('Click on the link to confirm you want to sign in. This link will expire in %s. https://example.com/login/verify', $expiresIn), $message->getSubject());
    }
}
