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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\Service\ResetInterface;

class ResetServicesListenerTest extends TestCase
{
    public static function provideResetServices(): iterable
    {
        yield [true];
        yield [false];
    }

    #[DataProvider('provideResetServices')]
    public function testResetServices(bool $shouldReset)
    {
        $resettableService = $this->createMock(ResetInterface::class);
        $resettableService->expects($shouldReset ? $this->once() : $this->never())->method('reset');
        $servicesResetter = new ServicesResetter(new \ArrayIterator(['foo' => $resettableService]), ['foo' => 'reset']);

        $event = new WorkerRunningEvent($this->createMock(Worker::class), !$shouldReset);

        $resetListener = new ResetServicesListener($servicesResetter);
        $resetListener->resetServices($event);
    }

    public function testResetServicesAtStop()
    {
        $resettableService = $this->createMock(ResetInterface::class);
        $resettableService->expects($this->once())->method('reset');
        $servicesResetter = new ServicesResetter(new \ArrayIterator(['foo' => $resettableService]), ['foo' => 'reset']);

        $event = new WorkerStoppedEvent($this->createMock(Worker::class));

        $resetListener = new ResetServicesListener($servicesResetter);
        $resetListener->resetServicesAtStop($event);
    }
}
