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
            $session = self::createMock(SessionInterface::class);

            $request = new Request();
            $request->setSession($session);
            $requestStack = self::getMockBuilder(RequestStack::class)->setMethods(['getSession'])->getMock();
            $requestStack->push($request);
            $requestStack->expects(self::any())->method('getSession')->willReturnCallback(function () use ($session, &$sessionAccess) {
                ++$sessionAccess;

                $session->expects(self::once())
                        ->method('getMetadataBag');

                return $session;
            });

            return $requestStack;
        }]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
        $tokenStorage = new TokenStorage();
        $trackingStorage = new UsageTrackingTokenStorage($tokenStorage, $sessionLocator);

        self::assertNull($trackingStorage->getToken());
        $token = new NullToken();

        $trackingStorage->setToken($token);
        self::assertSame($token, $trackingStorage->getToken());
        self::assertSame($token, $tokenStorage->getToken());
        self::assertSame(0, $sessionAccess);

        $trackingStorage->enableUsageTracking();
        self::assertSame($token, $trackingStorage->getToken());
        self::assertSame(1, $sessionAccess);

        $trackingStorage->disableUsageTracking();
        self::assertSame($token, $trackingStorage->getToken());
        self::assertSame(1, $sessionAccess);
    }

    public function testWithoutMainRequest()
    {
        $locator = new class(['request_stack' => function () {
            return new RequestStack();
        }]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
        $tokenStorage = new TokenStorage();
        $trackingStorage = new UsageTrackingTokenStorage($tokenStorage, $locator);
        $trackingStorage->enableUsageTracking();

        self::assertNull($trackingStorage->getToken());
    }
}
