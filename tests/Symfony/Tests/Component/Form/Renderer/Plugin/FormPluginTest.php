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

use Symfony\Component\Form\Renderer\Plugin\FormPlugin;

class FormPluginTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->markTestSkipped('Move me to Type tests');
    }

    public function testSetUp()
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\ThemeRendererInterface');

        $renderer->expects($this->at(0))
                ->method('setVar')
                ->with($this->equalTo('fields'), $this->equalTo(array()));

        $renderer->expects($this->at(1))
                ->method('setVar')
                ->with($this->equalTo('multipart'), $this->equalTo(false));


        $plugin = new FormPlugin();
        $plugin->setUp($form, $renderer);
    }

}