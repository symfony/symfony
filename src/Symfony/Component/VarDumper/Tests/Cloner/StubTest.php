<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Cloner;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\Stub;

final class StubTest extends TestCase
{
    public function testUnserializeNullValue()
    {
        $stub = new Stub();
        $stub->value = null;

        $stub = unserialize(serialize($stub));

        self::assertNull($stub->value);
    }

    public function testUnserializeNullInTypedProperty()
    {
        $stub = new MyStub();
        $stub->myProp = null;

        $stub = unserialize(serialize($stub));

        self::assertNull($stub->myProp);
    }

    public function testUninitializedStubPropertiesAreLeftUninitialized()
    {
        $stub = new MyStub();

        $stub = unserialize(serialize($stub));

        $r = new \ReflectionProperty(MyStub::class, 'myProp');
        self::assertFalse($r->isInitialized($stub));
    }
}

final class MyStub extends Stub
{
    public mixed $myProp;
}
