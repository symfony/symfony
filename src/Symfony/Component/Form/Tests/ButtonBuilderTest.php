<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ButtonBuilder;
use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * @author Alexander Cheprasov <cheprasov.84@ya.ru>
 */
class ButtonBuilderTest extends TestCase
{
    public function getValidNames()
    {
        return [
            ['reset'],
            ['submit'],
            ['foo'],
            ['0'],
            [0],
        ];
    }

    /**
     * @dataProvider getValidNames
     */
    public function testValidNames($name)
    {
        $this->assertInstanceOf('\Symfony\Component\Form\ButtonBuilder', new ButtonBuilder($name));
    }

    /**
     * @group legacy
     */
    public function testNameContainingIllegalCharacters()
    {
        $this->assertInstanceOf('\Symfony\Component\Form\ButtonBuilder', new ButtonBuilder('button[]'));
    }

    public function getInvalidNames()
    {
        return [
            [''],
            [false],
            [null],
        ];
    }

    /**
     * @dataProvider getInvalidNames
     */
    public function testInvalidNames($name)
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Buttons cannot have empty names.');
        } else {
            $this->setExpectedException(InvalidArgumentException::class, 'Buttons cannot have empty names.');
        }
        new ButtonBuilder($name);
    }
}
