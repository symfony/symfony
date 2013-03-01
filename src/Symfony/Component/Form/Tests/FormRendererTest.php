<?php

namespace Symfony\Component\Form\Test;

class FormRendererTest extends \PHPUnit_Framework_TestCase
{
    public function testHumanize()
    {
        $renderer = $this->getMockBuilder('Symfony\Component\Form\FormRenderer')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->assertEquals('Is active', $renderer->humanize('is_active'));
        $this->assertEquals('Is active', $renderer->humanize('isActive'));
    }
}
