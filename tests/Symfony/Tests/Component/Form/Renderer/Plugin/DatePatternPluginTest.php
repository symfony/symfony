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

use Symfony\Component\Form\Renderer\Plugin\DatePatternPlugin;

class DatePatternPluginTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->markTestSkipped('Move me to Type tests');
    }

    public function testen_US()
    {

        $intl = new \IntlDateFormatter("en_US" ,\IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);

        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $renderer = $this->getMock('Symfony\Component\Form\Renderer\ThemeRendererInterface');

        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('date_pattern'), $this->equalTo('{{ month }}/{{ day }}/{{ year }}'));
        $plugin = new DatePatternPlugin($intl);
        $plugin->setUp($form, $renderer);
    }

    public function testen_GB()
    {

        $intl = new \IntlDateFormatter("en_GB" ,\IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);

        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\ThemeRendererInterface');

        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('date_pattern'), $this->equalTo('{{ day }}/{{ month }}/{{ year }}'));
        $plugin = new DatePatternPlugin($intl);
        $plugin->setUp($form, $renderer);
    }

    public function testde_DE()
    {

        $intl = new \IntlDateFormatter("de_DE" ,\IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);

        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $renderer = $this->getMock('Symfony\Component\Form\Renderer\ThemeRendererInterface');

        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('date_pattern'), $this->equalTo('{{ day }}.{{ month }}.{{ year }}'));
        $plugin = new DatePatternPlugin($intl);
        $plugin->setUp($form, $renderer);
    }

    public function testDefault()
    {

        $intl = new \IntlDateFormatter("de_DE" ,\IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);

        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $renderer = $this->getMock('Symfony\Component\Form\Renderer\ThemeRendererInterface');

        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('date_pattern'), $this->equalTo('{{ year }}-{{ month }}-{{ day }}'));
        $plugin = new DatePatternPlugin($intl);
        $plugin->setUp($form, $renderer);
    }
}
