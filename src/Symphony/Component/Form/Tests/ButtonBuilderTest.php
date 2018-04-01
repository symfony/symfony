<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\ButtonBuilder;
use Symphony\Component\Form\Exception\InvalidArgumentException;

/**
 * @author Alexander Cheprasov <cheprasov.84@ya.ru>
 */
class ButtonBuilderTest extends TestCase
{
    public function getValidNames()
    {
        return array(
            array('reset'),
            array('submit'),
            array('foo'),
            array('0'),
            array(0),
            array('button[]'),
        );
    }

    /**
     * @dataProvider getValidNames
     */
    public function testValidNames($name)
    {
        $this->assertInstanceOf('\Symphony\Component\Form\ButtonBuilder', new ButtonBuilder($name));
    }

    public function getInvalidNames()
    {
        return array(
            array(''),
            array(false),
            array(null),
        );
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
