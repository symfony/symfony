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

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Symfony\Bridge\PhpUnit\Extension\DisableClockMockSubscriber;
use Symfony\Bridge\PhpUnit\Extension\DisableDnsMockSubscriber;
use Symfony\Bridge\PhpUnit\Extension\EnableClockMockSubscriber;
use Symfony\Bridge\PhpUnit\Extension\RegisterClockMockSubscriber;
use Symfony\Bridge\PhpUnit\Extension\RegisterDnsMockSubscriber;
use Symfony\Component\ErrorHandler\DebugClassLoader;

class SymfonyExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if (class_exists(DebugClassLoader::class)) {
            DebugClassLoader::enable();
        }

        if ($parameters->has('clock-mock-namespaces')) {
            foreach (explode(',', $parameters->get('clock-mock-namespaces')) as $namespace) {
                ClockMock::register($namespace.'\DummyClass');
            }
        }

        $facade->registerSubscriber(new RegisterClockMockSubscriber());
        $facade->registerSubscriber(new EnableClockMockSubscriber());
        $facade->registerSubscriber(new DisableClockMockSubscriber());

        if ($parameters->has('dns-mock-namespaces')) {
            foreach (explode(',', $parameters->get('dns-mock-namespaces')) as $namespace) {
                DnsMock::register($namespace.'\DummyClass');
            }
        }

        $facade->registerSubscriber(new RegisterDnsMockSubscriber());
        $facade->registerSubscriber(new DisableDnsMockSubscriber());
    }
}
