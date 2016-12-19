<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

class BaseDateTimeTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     * @expectedExceptionMessage this_timezone_does_not_exist
     */
    public function testConstructFailsIfInputTimezoneIsInvalid()
    {
        $this->getMockBuilder('Symfony\Component\Form\Extension\Core\DataTransformer\BaseDateTimeTransformer')->setConstructorArgs(array('this_timezone_does_not_exist'))->getMock();
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     * @expectedExceptionMessage that_timezone_does_not_exist
     */
    public function testConstructFailsIfOutputTimezoneIsInvalid()
    {
        $this->getMockBuilder('Symfony\Component\Form\Extension\Core\DataTransformer\BaseDateTimeTransformer')->setConstructorArgs(array(null, 'that_timezone_does_not_exist'))->getMock();
    }
}
