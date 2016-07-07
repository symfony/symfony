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

/**
 * @author Alexander Cheprasov <cheprasov.84@ya.ru>
 */
class ButtonBuilderTest extends \PHPUnit_Framework_TestCase
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
        $this->assertInstanceOf('\Symfony\Component\Form\ButtonBuilder', new ButtonBuilder($name));
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
        $this->setExpectedException(
            '\Symfony\Component\Form\Exception\InvalidArgumentException',
            'Buttons cannot have empty names.'
        );
        new ButtonBuilder($name);
    }
}
