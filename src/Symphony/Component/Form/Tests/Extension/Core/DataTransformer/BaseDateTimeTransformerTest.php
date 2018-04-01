<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;

class BaseDateTimeTransformerTest extends TestCase
{
    /**
     * @expectedException \Symphony\Component\Form\Exception\InvalidArgumentException
     * @expectedExceptionMessage this_timezone_does_not_exist
     */
    public function testConstructFailsIfInputTimezoneIsInvalid()
    {
        $this->getMockBuilder('Symphony\Component\Form\Extension\Core\DataTransformer\BaseDateTimeTransformer')->setConstructorArgs(array('this_timezone_does_not_exist'))->getMock();
    }

    /**
     * @expectedException \Symphony\Component\Form\Exception\InvalidArgumentException
     * @expectedExceptionMessage that_timezone_does_not_exist
     */
    public function testConstructFailsIfOutputTimezoneIsInvalid()
    {
        $this->getMockBuilder('Symphony\Component\Form\Extension\Core\DataTransformer\BaseDateTimeTransformer')->setConstructorArgs(array(null, 'that_timezone_does_not_exist'))->getMock();
    }
}
