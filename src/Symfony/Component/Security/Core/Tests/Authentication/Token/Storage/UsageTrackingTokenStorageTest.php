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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\UsageTrackingTokenStorage;
use Symfony\Contracts\Service\ServiceLocatorTrait;

class UsageTrackingTokenStorageTest extends TestCase
{
    public function testGetSetToken()
    {
        $sessionAccess = 0;
        $sessionLocator = new class(['request_stack' => function () use (&$sessionAccess) {
            $session = $this->createMock(SessionInterface::class);

            $request = new Request();
            $request->setSession($session);
            $requestStack = $this->getMockBuilder(RequestStack::class)->onlyMethods(['getSession'])->getMock();
            $requestStack->push($request);
            $requestStack->expects($this->any())->method('getSession')->willReturnCallback(function () use ($session, &$sessionAccess) {
                ++$sessionAccess;

                $session->expects($this->once())
                        ->method('getMetadataBag');

                return $session;
            });

            return $requestStack;
        }]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
        $tokenStorage = new TokenStorage();
        $trackingStorage = new UsageTrackingTokenStorage($tokenStorage, $sessionLocator);

        $this->assertNull($trackingStorage->getToken());
        $token = new NullToken();

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

    public function testWithoutMainRequest()
    {
        $locator = new class(['request_stack' => fn () => new RequestStack()]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
        $tokenStorage = new TokenStorage();
        $trackingStorage = new UsageTrackingTokenStorage($tokenStorage, $locator);
        $trackingStorage->enableUsageTracking();

        $this->assertNull($trackingStorage->getToken());
    }
}
