<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Passport\Badge;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class UserBadgeTest extends TestCase
{
    public function testUserNotFound()
    {
        $badge = new UserBadge('dummy', fn () => null);
        $this->expectException(UserNotFoundException::class);
        $badge->getUser();
    }
}
