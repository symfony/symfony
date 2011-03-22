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

use Symfony\Component\Form\Renderer\Plugin\EnctypePlugin;

use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\FieldInterface;

class EnctypePluginTest extends \PHPUnit_Framework_TestCase
{

    public function testSetUpIsMultiPart()
    {
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $field->expects($this->any())
              ->method('isMultipart')
              ->will($this->returnValue(true));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('enctype'), $this->equalTo('enctype="multipart/form-data"'));

        $plugin = new EnctypePlugin($field);
        $plugin->setUp($renderer);
    }

    public function testSetUpIsNotMultiPart()
    {
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $field->expects($this->any())
              ->method('isMultipart')
              ->will($this->returnValue(false));

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('enctype'), $this->equalTo(''));

        $plugin = new EnctypePlugin($field);
        $plugin->setUp($renderer);
    }
}