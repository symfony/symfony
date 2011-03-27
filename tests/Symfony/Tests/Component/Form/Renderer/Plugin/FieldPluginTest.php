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

use Symfony\Component\Form\Renderer\Plugin\FieldPlugin;

class FieldPluginTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->markTestSkipped('Move me to Type tests');
    }

    public function testSetUp()
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $form->expects($this->any())
              ->method('getClientData')
              ->will($this->returnValue('bar'));

        $form->expects($this->any())
              ->method('hasParent')
              ->will($this->returnValue(false));

        $form->expects($this->any())
              ->method('getName')
              ->will($this->returnValue('The_Name'));

        $form->expects($this->any())
              ->method('getErrors')
              ->will($this->returnValue('someerrors'));

        $form->expects($this->any())
              ->method('isDisabled')
              ->will($this->returnValue(false));

        $form->expects($this->any())
              ->method('isRequired')
              ->will($this->returnValue(true));


        $renderer = $this->getMock('Symfony\Component\Form\Renderer\ThemeRendererInterface');

        $renderer->expects($this->at(0))
                ->method('setVar')
                ->with($this->equalTo('renderer'), $this->equalTo($renderer));

        $renderer->expects($this->at(1))
                ->method('setVar')
                ->with($this->equalTo('id'), $this->equalTo('The_Name'));

        $renderer->expects($this->at(2))
                ->method('setVar')
                ->with($this->equalTo('name'), $this->equalTo('The_Name'));

        $renderer->expects($this->at(3))
                ->method('setVar')
                ->with($this->equalTo('errors'), $this->equalTo('someerrors'));

        $renderer->expects($this->at(4))
                ->method('setVar')
                ->with($this->equalTo('value'), $this->equalTo('bar'));

        $renderer->expects($this->at(5))
                ->method('setVar')
                ->with($this->equalTo('disabled'), $this->equalTo(false));

        $renderer->expects($this->at(6))
                ->method('setVar')
                ->with($this->equalTo('required'), $this->equalTo(true));

        $renderer->expects($this->at(7))
                ->method('setVar')
                ->with($this->equalTo('class'), $this->equalTo(null));

        $renderer->expects($this->at(8))
                ->method('setVar')
                ->with($this->equalTo('max_length'), $this->equalTo(null));

        $renderer->expects($this->at(9))
                ->method('setVar')
                ->with($this->equalTo('size'), $this->equalTo(null));

        $renderer->expects($this->at(10))
                ->method('setVar')
                ->with($this->equalTo('label'), $this->equalTo('The name'));


        $plugin = new FieldPlugin();
        $plugin->setUp($form, $renderer);
    }

}