<?php

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

class BaseDateTimeTransformerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException \InvalidArgumentException
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
     * @expectedException \InvalidArgumentException
     */
    public function testConstructFailsIfOutputTimezoneIsInvalid()
    {
        $this->getMock(
            'Symfony\Component\Form\Extension\Core\DataTransformer\BaseDateTimeTransformer',
            array(),
            array('UTC', 'this_timezone_does_not_exist')
        );
    }
}
