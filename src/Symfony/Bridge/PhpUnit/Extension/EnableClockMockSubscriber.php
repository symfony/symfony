<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Extension;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Metadata\Group;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bridge\PhpUnit\Metadata\AttributeReader;

/**
 * @internal
 */
class EnableClockMockSubscriber implements PreparationStartedSubscriber
{
    public function __construct(
        private AttributeReader $reader,
    ) {
    }

    public function notify(PreparationStarted $event): void
    {
        $test = $event->test();

        if (!$test instanceof TestMethod) {
            return;
        }

        foreach ($test->metadata() as $metadata) {
            if ($metadata instanceof Group && 'time-sensitive' === $metadata->groupName()) {
                ClockMock::withClockMock(true);
                break;
            }
        }

        if ($this->reader->forClassAndMethod($test->className(), $test->methodName(), TimeSensitive::class)) {
            ClockMock::withClockMock(true);
        }
    }
}
