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
use PHPUnit\Event\TestSuite\Loaded;
use PHPUnit\Event\TestSuite\LoadedSubscriber;
use PHPUnit\Metadata\Group;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bridge\PhpUnit\Metadata\AttributeReader;

/**
 * @internal
 */
class RegisterClockMockSubscriber implements LoadedSubscriber
{
    public function __construct(
        private AttributeReader $reader,
    ) {
    }

    public function notify(Loaded $event): void
    {
        foreach ($event->testSuite()->tests() as $test) {
            if (!$test instanceof TestMethod) {
                continue;
            }

            foreach ($test->metadata() as $metadata) {
                if ($metadata instanceof Group && 'time-sensitive' === $metadata->groupName()) {
                    ClockMock::register($test->className());
                }
            }

            foreach ($this->reader->forClassAndMethod($test->className(), $test->methodName(), TimeSensitive::class) as $attribute) {
                ClockMock::register($attribute->class ?? $test->className());
            }
        }
    }
}
