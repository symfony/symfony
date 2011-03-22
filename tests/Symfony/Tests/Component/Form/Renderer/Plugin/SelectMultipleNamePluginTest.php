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

use Symfony\Component\Form\Renderer\Plugin\SelectMultipleNamePlugin;

use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\FieldInterface;

class SelectMultipleNamePluginTest extends \PHPUnit_Framework_TestCase
{

    public function testSetUp()
    {
        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');
        $renderer->expects($this->once())
                ->method('getVar')
                ->with($this->equalTo('name'))
                ->will($this->returnValue('multiname'));

        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('name'), $this->equalTo('multiname[]'));

        $plugin = new SelectMultipleNamePlugin();
        $plugin->setUp($renderer);
    }
}