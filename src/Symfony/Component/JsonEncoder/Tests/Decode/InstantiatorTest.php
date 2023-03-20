<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Decode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Decode\Instantiator;
use Symfony\Component\JsonEncoder\Exception\UnexpectedValueException;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;

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

        (new Instantiator())->instantiate(ClassicDummy::class, [
            'id' => ['an', 'array'],
        ]);
    }
}
