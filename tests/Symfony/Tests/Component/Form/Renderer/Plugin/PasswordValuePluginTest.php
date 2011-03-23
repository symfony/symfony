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

use Symfony\Component\Form\Renderer\Plugin\PasswordValuePlugin;

class PasswordValuePluginTest extends \PHPUnit_Framework_TestCase
{

    public function testIsSubmittedSetUp()
    {
        $field = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $field->expects($this->any())
              ->method('getClientData')
              ->will($this->returnValue('pAs5w0rd'));

        $field->expects($this->any())
              ->method('isBound')
              ->will($this->returnValue(true));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\FormRendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('value'), $this->equalTo('pAs5w0rd'));

        $plugin = new PasswordValuePlugin(false);
        $plugin->setUp($field, $renderer);
    }

    public function testIsNotSubmittedSetUp()
    {
        $field = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $field->expects($this->any())
              ->method('getClientData')
              ->will($this->returnValue('pAs5w0rd'));

        $field->expects($this->any())
              ->method('isBound')
              ->will($this->returnValue(false));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\FormRendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('value'), $this->equalTo(''));

        $plugin = new PasswordValuePlugin(false);
        $plugin->setUp($field, $renderer);
    }
}