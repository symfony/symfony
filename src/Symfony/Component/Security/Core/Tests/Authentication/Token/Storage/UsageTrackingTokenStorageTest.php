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
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\UsageTrackingTokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Service\ServiceLocatorTrait;

class UsageTrackingTokenStorageTest extends TestCase
{
    public function testGetSetToken()
    {
        $sessionAccess = 0;
        $sessionLocator = new class(['session' => function () use (&$sessionAccess) {
            ++$sessionAccess;

            $session = $this->createMock(SessionInterface::class);
            $session->expects($this->once())
                    ->method('getMetadataBag');

            return $session;
        }]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
        $tokenStorage = new TokenStorage();
        $trackingStorage = new UsageTrackingTokenStorage($tokenStorage, $sessionLocator);

        $this->assertNull($trackingStorage->getToken());
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();

        $trackingStorage->setToken($token);
        $this->assertSame($token, $trackingStorage->getToken());
        $this->assertSame($token, $tokenStorage->getToken());
        $this->assertSame(0, $sessionAccess);

        $trackingStorage->enableUsageTracking();
        $this->assertSame($token, $trackingStorage->getToken());
        $this->assertSame(1, $sessionAccess);

        $trackingStorage->disableUsageTracking();
        $this->assertSame($token, $trackingStorage->getToken());
        $this->assertSame(1, $sessionAccess);
    }
}
