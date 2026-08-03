<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\DelegatingCsrfTokenManager;

class DelegatingCsrfTokenManagerTest extends TestCase
{
    public function testTokensAreHandledByTheManagerRegisteredForTheirId()
    {
        $logoutManager = $this->createMock(CsrfTokenManagerInterface::class);
        $logoutManager->expects($this->once())->method('getToken')->with('logout')->willReturn(new CsrfToken('logout', 'T0K3N'));
        $logoutManager->expects($this->once())->method('refreshToken')->with('logout')->willReturn(new CsrfToken('logout', 'FR3SH'));
        $logoutManager->expects($this->once())->method('removeToken')->with('logout')->willReturn('T0K3N');
        $logoutManager->expects($this->once())->method('isTokenValid')->willReturn(true);

        $fallbackManager = $this->createMock(CsrfTokenManagerInterface::class);
        $fallbackManager->expects($this->never())->method($this->anything());

        $manager = new DelegatingCsrfTokenManager($fallbackManager, $this->createLocator(['logout' => $logoutManager]));

        $this->assertSame('T0K3N', $manager->getToken('logout')->getValue());
        $this->assertSame('FR3SH', $manager->refreshToken('logout')->getValue());
        $this->assertSame('T0K3N', $manager->removeToken('logout'));
        $this->assertTrue($manager->isTokenValid(new CsrfToken('logout', 'T0K3N')));
    }

    public function testTokensWithNoDedicatedManagerAreHandledByTheDecoratedOne()
    {
        $logoutManager = $this->createMock(CsrfTokenManagerInterface::class);
        $logoutManager->expects($this->never())->method($this->anything());

        $fallbackManager = $this->createMock(CsrfTokenManagerInterface::class);
        $fallbackManager->expects($this->once())->method('getToken')->with('submit')->willReturn(new CsrfToken('submit', 'T0K3N'));
        $fallbackManager->expects($this->once())->method('refreshToken')->with('submit')->willReturn(new CsrfToken('submit', 'FR3SH'));
        $fallbackManager->expects($this->once())->method('removeToken')->with('submit')->willReturn('T0K3N');
        $fallbackManager->expects($this->once())->method('isTokenValid')->willReturn(false);

        $manager = new DelegatingCsrfTokenManager($fallbackManager, $this->createLocator(['logout' => $logoutManager]));

        $this->assertSame('T0K3N', $manager->getToken('submit')->getValue());
        $this->assertSame('FR3SH', $manager->refreshToken('submit')->getValue());
        $this->assertSame('T0K3N', $manager->removeToken('submit'));
        $this->assertFalse($manager->isTokenValid(new CsrfToken('submit', 'T0K3N')));
    }

    /**
     * @param array<string, CsrfTokenManagerInterface> $managers
     */
    private function createLocator(array $managers): ContainerInterface
    {
        return new class($managers) implements ContainerInterface {
            public function __construct(private array $managers)
            {
            }

            public function has(string $id): bool
            {
                return isset($this->managers[$id]);
            }

            public function get(string $id): mixed
            {
                return $this->managers[$id];
            }
        };
    }
}
