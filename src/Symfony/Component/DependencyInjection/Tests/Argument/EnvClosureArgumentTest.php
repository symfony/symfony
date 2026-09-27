<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Argument;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\EnvClosureArgument;

class EnvClosureArgumentTest extends TestCase
{
    public function testSerializationWithoutDefault()
    {
        $argument = unserialize(serialize(new EnvClosureArgument('%env(FOO)%')));

        $this->assertSame('%env(FOO)%', $argument->getValue());
        $this->assertNull($argument->getDefault());
        $this->assertFalse($argument->isStringable());
    }

    public function testSerializationWithDefault()
    {
        $argument = unserialize(serialize(new EnvClosureArgument('%env(FOO)%', 'bar', true)));

        $this->assertSame('%env(FOO)%', $argument->getValue());
        $this->assertSame('bar', $argument->getDefault());
        $this->assertTrue($argument->isStringable());
    }
}
