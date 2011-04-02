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
    private $themeFactory;

    private $renderer;

    protected function setUp()
    {
        $this->themeFactory = $this->getMock('Symfony\Component\Form\Renderer\Theme\ThemeFactoryInterface');
        $this->renderer = new ThemeRenderer($this->themeFactory);
    }

    public function testArrayAccess()
    {
        $fields = array(
            'foo' => $this->getMock('Symfony\Tests\Component\Form\FormInterface'),
            'bar' => $this->getMock('Symfony\Tests\Component\Form\FormInterface'),
        );

        $this->renderer->setChildren($fields);

        $this->assertTrue(isset($this->renderer['foo']));
        $this->assertTrue(isset($this->renderer['bar']));
        $this->assertSame($fields['bar'], $this->renderer['bar']);
    }

    public function testIterator()
    {
        $fields = array(
            'foo' => $this->getMock('Symfony\Tests\Component\Form\FormInterface'),
            'bar' => $this->getMock('Symfony\Tests\Component\Form\FormInterface'),
        );

        $this->renderer->setChildren($fields);

        $this->assertFalse($this->renderer->isRendered());
        $this->assertEquals($fields, iterator_to_array($this->renderer));
        $this->assertTrue($this->renderer->isRendered());
    }

    public function testIsChoiceSelected_intZeroIsNotEmptyString()
    {
        $this->renderer->setVar('choices', array(
            0 => 'foo',
            '' => 'bar',
        ));

        $this->renderer->setVar('value', 0);

        $this->assertTrue($this->renderer->isChoiceSelected(0));
        $this->assertFalse($this->renderer->isChoiceSelected(''));
    }

    public function testIsChoiceSelected_emptyStringIsNotIntZero()
    {
        $this->renderer->setVar('choices', array(
            0 => 'foo',
            '' => 'bar',
        ));

        $this->renderer->setVar('value', '');

        $this->assertFalse($this->renderer->isChoiceSelected(0));
        $this->assertTrue($this->renderer->isChoiceSelected(''));
    }

    public function testIsChoiceSelected_intZeroEqualsStringZero()
    {
        $this->renderer->setVar('choices', array(
            0 => 'foo',
        ));

        $this->renderer->setVar('value', 0);

        $this->assertTrue($this->renderer->isChoiceSelected(0));
        $this->assertTrue($this->renderer->isChoiceSelected('0'));
    }

    public function testIsChoiceSelected_stringZeroEqualsIntZero()
    {
        $this->renderer->setVar('choices', array(
            0 => 'foo',
        ));

        $this->renderer->setVar('value', '0');

        $this->assertTrue($this->renderer->isChoiceSelected(0));
        $this->assertTrue($this->renderer->isChoiceSelected('0'));
    }
}
