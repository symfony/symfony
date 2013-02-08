<?php

namespace Symfony\Component\Form\Test;

use Symfony\Component\Form\FormRenderer;

class FormRendererTest extends \PHPUnit_Framework_TestCase
{
    public function testHumanize()
    {
        $engine = $this->getMock('Symfony\Component\Form\FormRendererEngineInterface');
        $renderer = new FormRenderer($engine);

        $this->assertEquals('Is active', $renderer->humanize('is_active'));
        $this->assertEquals('Is active', $renderer->humanize('isActive'));

        $renderer = null;
    }
}