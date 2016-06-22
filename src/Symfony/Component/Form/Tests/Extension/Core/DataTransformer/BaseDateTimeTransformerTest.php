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
        $this->getMock(
            'Symfony\Component\Form\Extension\Core\DataTransformer\BaseDateTimeTransformer',
            array(),
            array('this_timezone_does_not_exist')
        );
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     * @expectedExceptionMessage that_timezone_does_not_exist
     */
    public function testConstructFailsIfOutputTimezoneIsInvalid()
    {
        $this->getMock(
            'Symfony\Component\Form\Extension\Core\DataTransformer\BaseDateTimeTransformer',
            array(),
            array(null, 'that_timezone_does_not_exist')
        );
    }
}
