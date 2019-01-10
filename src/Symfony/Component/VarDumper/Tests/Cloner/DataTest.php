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
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;

class DataTest extends TestCase
{
    public function testBasicData()
    {
        $values = [1 => 123, 4.5, 'abc', null, false];
        $data = $this->cloneVar($values);
        $clonedValues = [];

        $this->assertInstanceOf(Data::class, $data);
        $this->assertCount(\count($values), $data);
        $this->assertFalse(isset($data->{0}));
        $this->assertFalse(isset($data[0]));

        foreach ($data as $k => $v) {
            $this->assertTrue(isset($data->{$k}));
            $this->assertTrue(isset($data[$k]));
            $this->assertSame(\gettype($values[$k]), $data->seek($k)->getType());
            $this->assertSame($values[$k], $data->seek($k)->getValue());
            $this->assertSame($values[$k], $data->{$k});
            $this->assertSame($values[$k], $data[$k]);
            $this->assertSame((string) $values[$k], (string) $data->seek($k));

            $clonedValues[$k] = $v->getValue();
        }

        $this->assertSame($values, $clonedValues);
    }

    public function testObject()
    {
        $data = $this->cloneVar(new \Exception('foo'));

        $this->assertSame('Exception', $data->getType());

        $this->assertSame('foo', $data->message);
        $this->assertSame('foo', $data->{Caster::PREFIX_PROTECTED.'message'});

        $this->assertSame('foo', $data['message']);
        $this->assertSame('foo', $data[Caster::PREFIX_PROTECTED.'message']);

        $this->assertStringMatchesFormat('Exception (count=%d)', (string) $data);
    }

    public function testArray()
    {
        $values = [[], [123]];
        $data = $this->cloneVar($values);

        $this->assertSame($values, $data->getValue(true));

        $children = $data->getValue();

        $this->assertInternalType('array', $children);

        $this->assertInstanceOf(Data::class, $children[0]);
        $this->assertInstanceOf(Data::class, $children[1]);

        $this->assertEquals($children[0], $data[0]);
        $this->assertEquals($children[1], $data[1]);

        $this->assertSame($values[0], $children[0]->getValue(true));
        $this->assertSame($values[1], $children[1]->getValue(true));
    }

    public function testStub()
    {
        $data = $this->cloneVar([new ClassStub('stdClass')]);
        $data = $data[0];

        $this->assertSame('string', $data->getType());
        $this->assertSame('stdClass', $data->getValue());
        $this->assertSame('stdClass', (string) $data);
    }

    public function testHardRefs()
    {
        $values = [[]];
        $values[1] = &$values[0];
        $values[2][0] = &$values[2];

        $data = $this->cloneVar($values);

        $this->assertSame([], $data[0]->getValue());
        $this->assertSame([], $data[1]->getValue());
        $this->assertEquals([$data[2]->getValue()], $data[2]->getValue(true));

        $this->assertSame('array (count=3)', (string) $data);
    }

    private function cloneVar($value)
    {
        $cloner = new VarCloner();

        return $cloner->cloneVar($value);
    }
}
