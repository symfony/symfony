<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

use Doctrine\Deprecations\Deprecation;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\BeforeTestMethodErrored;
use PHPUnit\Event\Test\BeforeTestMethodErroredSubscriber;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;
use PHPUnit\Metadata\Group;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Symfony\Bridge\PhpUnit\Attribute\DnsSensitive;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Bridge\PhpUnit\Extension\EnableClockMockSubscriber;
use Symfony\Bridge\PhpUnit\Extension\RegisterClockMockSubscriber;
use Symfony\Bridge\PhpUnit\Extension\RegisterDnsMockSubscriber;
use Symfony\Bridge\PhpUnit\Metadata\AttributeReader;
use Symfony\Component\ErrorHandler\DebugClassLoader;

class SymfonyExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if (class_exists(DebugClassLoader::class)) {
            DebugClassLoader::enable();
        }

        if (class_exists(Deprecation::class)) {
            Deprecation::withoutDeduplication();
        }

        $reader = new AttributeReader();

        if ($parameters->has('clock-mock-namespaces')) {
            foreach (explode(',', $parameters->get('clock-mock-namespaces')) as $namespace) {
                ClockMock::register($namespace.'\DummyClass');
            }
        }

        $facade->registerSubscriber(new RegisterClockMockSubscriber($reader));
        $facade->registerSubscriber(new EnableClockMockSubscriber($reader));
        $facade->registerSubscriber(new class($reader) implements ErroredSubscriber {
            public function __construct(private AttributeReader $reader)
            {
            }

            public function notify(Errored $event): void
            {
                SymfonyExtension::disableClockMock($event->test(), $this->reader);
                SymfonyExtension::disableDnsMock($event->test(), $this->reader);
            }
        });
        $facade->registerSubscriber(new class($reader) implements FinishedSubscriber {
            public function __construct(private AttributeReader $reader)
            {
            }

            public function notify(Finished $event): void
            {
                SymfonyExtension::disableClockMock($event->test(), $this->reader);
                SymfonyExtension::disableDnsMock($event->test(), $this->reader);
            }
        });
        $facade->registerSubscriber(new class($reader) implements SkippedSubscriber {
            public function __construct(private AttributeReader $reader)
            {
            }

            public function notify(Skipped $event): void
            {
                SymfonyExtension::disableClockMock($event->test(), $this->reader);
                SymfonyExtension::disableDnsMock($event->test(), $this->reader);
            }
        });

        if (interface_exists(BeforeTestMethodErroredSubscriber::class)) {
            $facade->registerSubscriber(new class($reader) implements BeforeTestMethodErroredSubscriber {
                public function __construct(private AttributeReader $reader)
                {
                }

                public function notify(BeforeTestMethodErrored $event): void
                {
                    if (method_exists($event, 'test')) {
                        SymfonyExtension::disableClockMock($event->test(), $this->reader);
                        SymfonyExtension::disableDnsMock($event->test(), $this->reader);
                    } else {
                        ClockMock::withClockMock(false);
                        DnsMock::withMockedHosts([]);
                    }
                }
            });
        }

        if ($parameters->has('dns-mock-namespaces')) {
            foreach (explode(',', $parameters->get('dns-mock-namespaces')) as $namespace) {
                DnsMock::register($namespace.'\DummyClass');
            }
        }

        $facade->registerSubscriber(new RegisterDnsMockSubscriber($reader));
    }

    /**
     * @internal
     */
    public static function disableClockMock(Test $test, AttributeReader $reader): void
    {
        if (self::hasGroup($test, 'time-sensitive', $reader, TimeSensitive::class)) {
            ClockMock::withClockMock(false);
        }
    }

    /**
     * @internal
     */
    public static function disableDnsMock(Test $test, AttributeReader $reader): void
    {
        if (self::hasGroup($test, 'dns-sensitive', $reader, DnsSensitive::class)) {
            DnsMock::withMockedHosts([]);
        }
    }

    /**
     * @internal
     */
    public static function hasGroup(Test $test, string $groupName, AttributeReader $reader, string $attribute): bool
    {
        if (!$test instanceof TestMethod) {
            return false;
        }

        foreach ($test->metadata() as $metadata) {
            if ($metadata instanceof Group && $groupName === $metadata->groupName()) {
                return true;
            }
        }

        return [] !== $reader->forClassAndMethod($test->className(), $test->methodName(), $attribute);
    }
}
