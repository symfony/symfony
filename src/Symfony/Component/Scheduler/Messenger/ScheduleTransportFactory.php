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
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Locator\ScheduleConfigLocatorInterface;
use Symfony\Component\Scheduler\Schedule\Schedule;
use Symfony\Component\Scheduler\State\StateFactoryInterface;

class ScheduleTransportFactory implements TransportFactoryInterface
{
    protected const DEFAULT_OPTIONS = [
        'cache' => null,
        'lock' => null,
    ];

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly ScheduleConfigLocatorInterface $schedules,
        private readonly StateFactoryInterface $stateFactory,
    ) {
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): ScheduleTransport
    {
        if ('schedule://' === $dsn) {
            throw new InvalidArgumentException('The Schedule DSN must contains a name, e.g. "schedule://default".');
        }
        if (false === $scheduleName = parse_url($dsn, \PHP_URL_HOST)) {
            throw new InvalidArgumentException(sprintf('The given Schedule DSN "%s" is invalid.', $dsn));
        }

        unset($options['transport_name']);
        $options += static::DEFAULT_OPTIONS;
        if (0 < \count($invalidOptions = array_diff_key($options, static::DEFAULT_OPTIONS))) {
            throw new InvalidArgumentException(sprintf('Invalid option(s) "%s" passed to the Schedule Messenger transport.', implode('", "', array_keys($invalidOptions))));
        }

        if (!$this->schedules->has($scheduleName)) {
            throw new InvalidArgumentException(sprintf('The schedule "%s" is not found.', $scheduleName));
        }

        return new ScheduleTransport(
            new Schedule(
                $this->clock,
                $this->stateFactory->create($scheduleName, $options),
                $this->schedules->get($scheduleName)
            )
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return self::isSupported($dsn);
    }

    final public static function isSupported(string $dsn): bool
    {
        return str_starts_with($dsn, 'schedule://');
    }
}
