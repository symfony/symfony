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

use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\FieldInterface;

class PasswordValuePluginTest extends \PHPUnit_Framework_TestCase
{

    public function testIsSubmittedSetUp()
    {
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $field->expects($this->any())
              ->method('getDisplayedData')
              ->will($this->returnValue('pAs5w0rd'));

        $field->expects($this->any())
              ->method('isSubmitted')
              ->will($this->returnValue(true));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('value'), $this->equalTo('pAs5w0rd'));

        $plugin = new PasswordValuePlugin($field,false);
        $plugin->setUp($renderer);
    }

    public function testIsNotSubmittedSetUp()
    {
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $field->expects($this->any())
              ->method('getDisplayedData')
              ->will($this->returnValue('pAs5w0rd'));

        $field->expects($this->any())
              ->method('isSubmitted')
              ->will($this->returnValue(false));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('value'), $this->equalTo(''));

        $plugin = new PasswordValuePlugin($field,false);
        $plugin->setUp($renderer);
    }
}