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

use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\FieldInterface;

class DatePatternPluginTest extends \PHPUnit_Framework_TestCase
{

    public function testen_US()
    {

        $intl = new \IntlDateFormatter("en_US" ,\IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');

        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('date_pattern'), $this->equalTo('{{ month }}/{{ day }}/{{ year }}'));
        $plugin = new DatePatternPlugin($intl);
        $plugin->setUp($renderer);
    }

    public function testen_GB()
    {

        $intl = new \IntlDateFormatter("en_GB" ,\IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');

        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('date_pattern'), $this->equalTo('{{ day }}/{{ month }}/{{ year }}'));
        $plugin = new DatePatternPlugin($intl);
        $plugin->setUp($renderer);
    }

    public function testde_DE()
    {

        $intl = new \IntlDateFormatter("de_DE" ,\IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');

        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('date_pattern'), $this->equalTo('{{ day }}.{{ month }}.{{ year }}'));
        $plugin = new DatePatternPlugin($intl);
        $plugin->setUp($renderer);
    }

    public function testDefault()
    {

        $intl = new \IntlDateFormatter("de_DE" ,\IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);

        $renderer = $this->getMock('Symfony\Component\Form\Renderer\RendererInterface');

        $renderer->expects($this->once())
                ->method('setVar')
                ->with($this->equalTo('date_pattern'), $this->equalTo('{{ year }}-{{ month }}-{{ day }}'));
        $plugin = new DatePatternPlugin($intl);
        $plugin->setUp($renderer);
    }
}