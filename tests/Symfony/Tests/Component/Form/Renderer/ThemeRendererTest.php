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
        $fields = array(
            'foo' => $this->getMock('Symfony\Tests\Component\Form\FormInterface'),
            'bar' => $this->getMock('Symfony\Tests\Component\Form\FormInterface'),
        );

        $themeFactory = $this->createThemeFactory();
        $renderer = new ThemeRenderer($themeFactory);
        $renderer->setChildren($fields);

        $this->assertTrue(isset($renderer['foo']));
        $this->assertTrue(isset($renderer['bar']));
        $this->assertSame($fields['bar'], $renderer['bar']);
    }

    public function testIterator()
    {
        $themeFactory = $this->createThemeFactory();

        $renderer = new ThemeRenderer($themeFactory);
        $renderer->setVar('fields', array('foo' => 'baz', 'bar' => 'baz'));

        $this->assertFalse($renderer->isRendered());

        foreach ($renderer AS $child) {
            $this->assertEquals('baz', $child);
        }

        $this->assertTrue($renderer->isRendered());
    }
}
