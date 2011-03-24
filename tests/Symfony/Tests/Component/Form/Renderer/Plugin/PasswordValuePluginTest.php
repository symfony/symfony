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
    protected function setUp()
    {
        $this->markTestSkipped('Move me to Type tests');
    }

    public function testIsSubmittedSetUp()
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $form->expects($this->any())
              ->method('getClientData')
              ->will($this->returnValue('pAs5w0rd'));

        $form->expects($this->any())
              ->method('isBound')
              ->will($this->returnValue(true));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\FormRendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('value'), $this->equalTo('pAs5w0rd'));

        $plugin = new PasswordValuePlugin(false);
        $plugin->setUp($form, $renderer);
    }

    public function testIsNotSubmittedSetUp()
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $form->expects($this->any())
              ->method('getClientData')
              ->will($this->returnValue('pAs5w0rd'));

        $form->expects($this->any())
              ->method('isBound')
              ->will($this->returnValue(false));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\FormRendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('value'), $this->equalTo(''));

        $plugin = new PasswordValuePlugin(false);
        $plugin->setUp($form, $renderer);
    }
}