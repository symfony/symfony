<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Read;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\UnexpectedValueException;
use Symfony\Component\JsonStreamer\Read\Instantiator;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;

class InstantiatorTest extends TestCase
{
    public function testInstantiate()
    {
        $expected = new ClassicDummy();
        $expected->id = 100;
        $expected->name = 'dummy';

        $properties = [
            'id' => 100,
            'name' => 'dummy',
        ];

        $this->assertEquals($expected, (new Instantiator())->instantiate(ClassicDummy::class, $properties));
    }

    public function testThrowOnInvalidProperty()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(\sprintf('Cannot assign array to property %s::$id of type int', ClassicDummy::class));

        (new Instantiator())->instantiate(ClassicDummy::class, [
            'id' => ['an', 'array'],
        ]);
    }
}
