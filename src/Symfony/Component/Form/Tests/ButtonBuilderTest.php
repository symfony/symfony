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

use Symfony\Component\Form\ButtonBuilder;
use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * @author Alexander Cheprasov <cheprasov.84@ya.ru>
 */
class ButtonBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return array
     */
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
     *
     * @param string $name
     */
    public function testValidNames($name)
    {
        $this->assertInstanceOf(ButtonBuilder::class, new ButtonBuilder($name));
    }

    /**
     * @return array
     */
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
     *
     * @param string $name
     */
    public function testInvalidNames($name)
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Buttons cannot have empty names.');
        new ButtonBuilder($name);
    }
}
