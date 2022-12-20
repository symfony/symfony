<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\CommandLoader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ContainerCommandLoaderTest extends TestCase
{
    public function testHas()
    {
        $loader = new ContainerCommandLoader(new ServiceLocator([
            'foo-service' => function () { return new Command('foo'); },
            'bar-service' => function () { return new Command('bar'); },
        ]), ['foo' => 'foo-service', 'bar' => 'bar-service']);

        self::assertTrue($loader->has('foo'));
        self::assertTrue($loader->has('bar'));
        self::assertFalse($loader->has('baz'));
    }

    public function testGet()
    {
        $loader = new ContainerCommandLoader(new ServiceLocator([
            'foo-service' => function () { return new Command('foo'); },
            'bar-service' => function () { return new Command('bar'); },
        ]), ['foo' => 'foo-service', 'bar' => 'bar-service']);

        self::assertInstanceOf(Command::class, $loader->get('foo'));
        self::assertInstanceOf(Command::class, $loader->get('bar'));
    }

    public function testGetUnknownCommandThrows()
    {
        self::expectException(CommandNotFoundException::class);
        (new ContainerCommandLoader(new ServiceLocator([]), []))->get('unknown');
    }

    public function testGetCommandNames()
    {
        $loader = new ContainerCommandLoader(new ServiceLocator([
            'foo-service' => function () { return new Command('foo'); },
            'bar-service' => function () { return new Command('bar'); },
        ]), ['foo' => 'foo-service', 'bar' => 'bar-service']);

        self::assertSame(['foo', 'bar'], $loader->getNames());
    }
}
