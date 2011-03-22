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

use Symfony\Component\Form\Renderer\Plugin\ParentNamePlugin;

class ParentNamePluginTest extends \PHPUnit_Framework_TestCase
{

    public function testSetUpHasParent()
    {
        $parentRenderer = $this->getMock('Symfony\Component\Form\Renderer\FormRendererInterface');
        $parentRenderer->expects($this->once())
                ->method('getVar')
                ->will($this->returnValue("parentName"));

        $parentField = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $parentField->expects($this->once())
              ->method('getRenderer')
              ->will($this->returnValue($parentRenderer));

        $field = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $field->expects($this->once())
              ->method('getParent')
              ->will($this->returnValue($parentField));

        $field->expects($this->once())
              ->method('hasParent')
              ->will($this->returnValue(true));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\FormRendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('name'), $this->equalTo('parentName'));

        $plugin = new ParentNamePlugin();
        $plugin->setUp($field, $renderer);
    }

    public function testSetUpNoParent()
    {
        
        $field = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $field->expects($this->never())
              ->method('getParent');

        $field->expects($this->once())
              ->method('hasParent')
              ->will($this->returnValue(false));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\FormRendererInterface');
        $renderer->expects($this->never())
                ->method('setVar');

        $plugin = new ParentNamePlugin();
        $plugin->setUp($field, $renderer);
    }
}