<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\ArgumentResolver\ArgumentResolver;
use Symfony\Component\Console\Input\ArrayInput;

class ArgumentResolverTest extends TestCase
{
    public function testUnresolvableIntersectionTypeIsReported()
    {
        $command = static fn (DummyInterfaceA&DummyInterfaceB $service) => 0;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('its type "%s&%s" cannot be auto-resolved', DummyInterfaceA::class, DummyInterfaceB::class));

        (new ArgumentResolver())->getArguments(new ArrayInput([]), $command);
    }

    public function testUnresolvableDnfTypeIsReported()
    {
        $command = static fn ((DummyInterfaceA&DummyInterfaceB)|DummyClass $service) => 0;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('its type "(%s&%s)|%s" cannot be auto-resolved', DummyInterfaceA::class, DummyInterfaceB::class, DummyClass::class));

        (new ArgumentResolver())->getArguments(new ArrayInput([]), $command);
    }
}

interface DummyInterfaceA
{
}

interface DummyInterfaceB
{
}

class DummyClass
{
}
