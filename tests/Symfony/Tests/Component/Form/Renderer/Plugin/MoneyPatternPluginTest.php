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

use Symfony\Component\Form\Renderer\Plugin\MoneyPatternPlugin;

class MoneyPatternPluginTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->markTestSkipped('Move me to Type tests');
    }

    public function testSetUpEur()
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $renderer = $this->getMock('Symfony\Component\Form\Renderer\ThemeRendererInterface');
        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('money_pattern'), $this->equalTo('€ {{ widget }}'));

        $plugin = new MoneyPatternPlugin('EUR');
        $plugin->setUp($form, $renderer);
    }

}
