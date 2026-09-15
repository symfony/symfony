<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\EventListener;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\EventListener\FirewallListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

class FirewallListenerTest extends TestCase
{
    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testTheDispatcherArgumentOfTheOldSignatureIsDeprecated()
    {
        $logoutUrlGenerator = $this->createMock(LogoutUrlGenerator::class);
        $logoutUrlGenerator->expects($this->once())->method('setCurrentFirewall')->with(null);

        $this->expectUserDeprecationMessage('Since symfony/security-bundle 8.2: Passing an event dispatcher to "Symfony\Bundle\SecurityBundle\EventListener\FirewallListener::__construct()" is deprecated, the argument will be removed in 9.0.');

        $listener = new FirewallListener($this->createStub(FirewallMapInterface::class), new EventDispatcher(), $logoutUrlGenerator);

        $listener->onKernelFinishRequest(new FinishRequestEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));
    }
}
