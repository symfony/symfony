<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Token\Storage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\UsageTrackingTokenStorage;

class UsageTrackingTokenStorageTest extends TestCase
{
    public function testGetSetToken()
    {
        $counter = 0;
        $usageTracker = function () use (&$counter) { ++$counter; };

        $tokenStorage = new UsageTrackingTokenStorage();
        $this->assertNull($tokenStorage->getToken());
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        $tokenStorage->setToken($token);
        $this->assertSame($token, $tokenStorage->getToken());

        $tokenStorage->setToken($token, $usageTracker);
        $this->assertSame($token, $tokenStorage->getToken(false));
        $this->assertSame(0, $counter);
        $this->assertSame($token, $tokenStorage->getToken());
        $this->assertSame(1, $counter);

        $tokenStorage->setToken(null, $usageTracker);
        $this->assertNull($tokenStorage->getToken(false));
        $this->assertSame(1, $counter);
        $this->assertNull($tokenStorage->getToken());
        $this->assertSame(2, $counter);
    }
}
