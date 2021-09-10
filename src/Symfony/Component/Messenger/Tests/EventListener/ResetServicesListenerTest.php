<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;

class ResetServicesListenerTest extends TestCase
{
    public function provideTests(): iterable
    {
        yield ['foo', true];
        yield ['bar', false];
    }

    /** @dataProvider provideTests */
    public function test(string $receiverName, bool $shouldReset)
    {
        $servicesResetter = $this->createMock(ServicesResetter::class);
        $servicesResetter->expects($shouldReset ? $this->once() : $this->never())->method('reset');

        $event = new class(new Envelope(new \stdClass()), $receiverName) extends AbstractWorkerMessageEvent {};

        $resetListener = new ResetServicesListener($servicesResetter, ['foo']);
        $resetListener->resetServices($event);
    }
}
