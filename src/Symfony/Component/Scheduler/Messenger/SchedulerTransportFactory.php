<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Messenger;

use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Generator\Checkpoint;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Component\Scheduler\Schedule;

/**
 * @experimental
 */
class SchedulerTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private readonly ContainerInterface $scheduleProviders,
        private readonly ClockInterface $clock = new Clock(),
    ) {
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): SchedulerTransport
    {
        if ('schedule://' === $dsn) {
            throw new InvalidArgumentException('The Schedule DSN must contains a name, e.g. "schedule://default".');
        }
        if (false === $scheduleName = parse_url($dsn, \PHP_URL_HOST)) {
            throw new InvalidArgumentException(sprintf('The given Schedule DSN "%s" is invalid.', $dsn));
        }
        if (!$this->scheduleProviders->has($scheduleName)) {
            throw new InvalidArgumentException(sprintf('The schedule "%s" is not found.', $scheduleName));
        }

        /** @var Schedule $schedule */
        $schedule = $this->scheduleProviders->get($scheduleName)->getSchedule();
        $checkpoint = new Checkpoint('scheduler_checkpoint_'.$scheduleName, $schedule->getLock(), $schedule->getState());

        return new SchedulerTransport(new MessageGenerator($schedule, $checkpoint, $this->clock));
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'schedule://');
    }
}
