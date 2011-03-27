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
        return $this->getMock('Symfony\Component\Form\Renderer\Theme\FormThemeFactoryInterface');
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
        $fields = array(
            'foo' => $this->getMock('Symfony\Tests\Component\Form\FormInterface'),
            'bar' => $this->getMock('Symfony\Tests\Component\Form\FormInterface'),
        );

        $themeFactory = $this->createThemeFactory();
        $renderer = new ThemeRenderer($themeFactory);
        $renderer->setChildren($fields);

        $this->assertFalse($renderer->isRendered());

        $this->assertEquals($fields, iterator_to_array($renderer));

        $this->assertTrue($renderer->isRendered());
    }
}
