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

        self::assertInstanceOf(Data::class, $data);
        self::assertCount(\count($values), $data);
        self::assertFalse(isset($data->{0}));
        self::assertFalse(isset($data[0]));

        foreach ($data as $k => $v) {
            self::assertTrue(isset($data->{$k}));
            self::assertTrue(isset($data[$k]));
            self::assertSame(\gettype($values[$k]), $data->seek($k)->getType());
            self::assertSame($values[$k], $data->seek($k)->getValue());
            self::assertSame($values[$k], $data->{$k});
            self::assertSame($values[$k], $data[$k]);
            self::assertSame((string) $values[$k], (string) $data->seek($k));

            $clonedValues[$k] = $v->getValue();
        }

        self::assertSame($values, $clonedValues);
    }

    public function testObject()
    {
        $data = $this->cloneVar(new \Exception('foo'));

        self::assertSame('Exception', $data->getType());

        self::assertSame('foo', $data->message);
        self::assertSame('foo', $data->{Caster::PREFIX_PROTECTED.'message'});

        self::assertSame('foo', $data['message']);
        self::assertSame('foo', $data[Caster::PREFIX_PROTECTED.'message']);

        self::assertStringMatchesFormat('Exception (count=%d)', (string) $data);
    }

    public function testArray()
    {
        $values = [[], [123]];
        $data = $this->cloneVar($values);

        self::assertSame($values, $data->getValue(true));

        $children = $data->getValue();

        self::assertIsArray($children);

        self::assertInstanceOf(Data::class, $children[0]);
        self::assertInstanceOf(Data::class, $children[1]);

        self::assertEquals($children[0], $data[0]);
        self::assertEquals($children[1], $data[1]);

        self::assertSame($values[0], $children[0]->getValue(true));
        self::assertSame($values[1], $children[1]->getValue(true));
    }

    public function testStub()
    {
        $data = $this->cloneVar([new ClassStub('stdClass')]);
        $data = $data[0];

        self::assertSame('string', $data->getType());
        self::assertSame('stdClass', $data->getValue());
        self::assertSame('stdClass', (string) $data);
    }

    public function testHardRefs()
    {
        $values = [[]];
        $values[1] = &$values[0];
        $values[2][0] = &$values[2];

        $data = $this->cloneVar($values);

        self::assertSame([], $data[0]->getValue());
        self::assertSame([], $data[1]->getValue());
        self::assertEquals([$data[2]->getValue()], $data[2]->getValue(true));

        self::assertSame('array (count=3)', (string) $data);
    }

    private function cloneVar($value)
    {
        $cloner = new VarCloner();

        return $cloner->cloneVar($value);
    }
}
