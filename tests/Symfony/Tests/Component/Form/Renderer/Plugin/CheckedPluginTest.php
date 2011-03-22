<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Renderer\Plugin;

use Symfony\Component\Form\Renderer\Plugin\CheckedPlugin;

use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\FieldInterface;

class CheckedPluginTest extends \PHPUnit_Framework_TestCase
{

    public function testSetUpTrue()
    {
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $field->expects($this->any())
              ->method('getData')
              ->will($this->returnValue(1));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('checked'), $this->equalTo(true));

        $plugin = new CheckedPlugin($field);
        $plugin->setUp($renderer);
    }

    public function testSetUpFalse()
    {
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $field->expects($this->any())
              ->method('getData')
              ->will($this->returnValue(0));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('checked'), $this->equalTo(false));

        $plugin = new CheckedPlugin($field);
        $plugin->setUp($renderer);
    }
}
