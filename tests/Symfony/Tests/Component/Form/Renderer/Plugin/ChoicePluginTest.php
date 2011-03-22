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

use Symfony\Component\Form\Renderer\Plugin\ChoicePlugin;

use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

class ChoicePluginTest extends \PHPUnit_Framework_TestCase
{

    public function testSetUp()
    {
        $field = $this->getMock('Symfony\Component\Form\ChoiceList\ChoiceListInterface');
        $field->expects($this->any())
              ->method('getOtherChoices')
              ->will($this->returnValue('somechoices'));
        $field->expects($this->any())
              ->method('getPreferredChoices')
              ->will($this->returnValue('somethingelse'));


        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');

        $renderer->expects($this->at(0))
                ->method('setVar')
                ->with($this->equalTo('choices'), $this->equalTo('somechoices'));

        $renderer->expects($this->at(1))
                ->method('setVar')
                ->with($this->equalTo('preferred_choices'), $this->equalTo('somethingelse'));

        $renderer->expects($this->at(2))
                ->method('setVar')
                ->with($this->equalTo('separator'), $this->equalTo('-------------------'));

        $renderer->expects($this->at(3))
                ->method('setVar')
                ->with($this->equalTo('choice_list'), $this->equalTo($field));

        $renderer->expects($this->at(4))
                ->method('setVar')
                ->with($this->equalTo('empty_value'), $this->equalTo(''));

        $plugin = new ChoicePlugin($field);
        $plugin->setUp($renderer);
    }
}