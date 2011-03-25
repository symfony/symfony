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

use Symfony\Component\Form\Renderer\Plugin\MaxLengthPlugin;

class MaxLengthPluginTest extends \PHPUnit_Framework_TestCase
{

    public function testSetUp()
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        
        $renderer = $this->getMock('Symfony\Component\Form\Renderer\FormRendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('max_length'), $this->equalTo(12));

        $plugin = new MaxLengthPlugin(12);
        $plugin->setUp($form, $renderer);

    }
}