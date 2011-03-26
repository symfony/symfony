<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Renderer;

use Symfony\Component\Form\Renderer\ThemeRenderer;

class ThemeRendererTest extends \PHPUnit_Framework_TestCase
{
    protected function createThemeFactory()
    {
        $themeFactory = $this->getMock('Symfony\Component\Form\Renderer\Theme\FormThemeFactoryInterface');
        $themeFactory->expects($this->once())
                     ->method('create')
                     ->will($this->returnValue($this->getMock('Symfony\Component\Form\Renderer\Theme\FormThemeInterface')));
        return $themeFactory;
    }

    public function testArrayAccess()
    {
        $themeFactory = $this->createThemeFactory();
        $renderer = new ThemeRenderer($themeFactory);
        $renderer->setVar('fields', array('foo' => 'baz', 'bar' => 'baz'));

        $this->assertTrue(isset($renderer['foo']));
        $this->assertTrue(isset($renderer['bar']));
        $this->assertEquals('baz', $renderer['bar']);
    }

    public function testIterator()
    {
        $themeFactory = $this->createThemeFactory();
        
        $renderer = new ThemeRenderer($themeFactory);
        $renderer->setVar('fields', array('foo' => 'baz', 'bar' => 'baz'));

        foreach ($renderer AS $child) {
            $this->assertEquals('baz', $child);
        }
    }
}
