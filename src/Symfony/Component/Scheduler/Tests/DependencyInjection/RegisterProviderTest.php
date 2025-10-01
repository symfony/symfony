<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\DependencyInjection\AddScheduleMessengerPass;
use Symfony\Component\Scheduler\Tests\Fixtures\SomeScheduleProvider;

class RegisterProviderTest extends TestCase
{
    public function testErrorOnMultipleProvidersForTheSameSchedule()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1);

        $container = new ContainerBuilder();

        $container->register('provider_a', SomeScheduleProvider::class)->addTag('scheduler.schedule_provider', ['name' => 'default']);
        $container->register('provider_b', SomeScheduleProvider::class)->addTag('scheduler.schedule_provider', ['name' => 'default']);

        (new AddScheduleMessengerPass())->process($container);
    }
}
