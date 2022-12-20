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
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\Worker;

class ResetServicesListenerTest extends TestCase
{
    public function provideResetServices(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * @dataProvider provideResetServices
     */
    public function testResetServices(bool $shouldReset)
    {
        $servicesResetter = self::createMock(ServicesResetter::class);
        $servicesResetter->expects($shouldReset ? self::once() : self::never())->method('reset');

        $event = new WorkerRunningEvent(self::createMock(Worker::class), !$shouldReset);

        $resetListener = new ResetServicesListener($servicesResetter);
        $resetListener->resetServices($event);
    }

    public function testResetServicesAtStop()
    {
        $servicesResetter = self::createMock(ServicesResetter::class);
        $servicesResetter->expects(self::once())->method('reset');

        $event = new WorkerStoppedEvent(self::createMock(Worker::class));

        $resetListener = new ResetServicesListener($servicesResetter);
        $resetListener->resetServicesAtStop($event);
    }
}
