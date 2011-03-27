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

class CheckedPluginTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->markTestSkipped('Move me to Type tests');
    }

    public function testSetUpTrue()
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $form->expects($this->once())
              ->method('getData')
              ->will($this->returnValue(1));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\ThemeRendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('checked'), $this->equalTo(true));

        $plugin = new CheckedPlugin();
        $plugin->setUp($form, $renderer);
    }

    public function testSetUpFalse()
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $form->expects($this->once())
              ->method('getData')
              ->will($this->returnValue(0));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\ThemeRendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('checked'), $this->equalTo(false));

        $plugin = new CheckedPlugin();
        $plugin->setUp($form, $renderer);
    }

}
