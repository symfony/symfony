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

    public function considersEqualProvider()
    {
        return array(
            // the first argument given here must be a valid array key
            //     = integers and non-integer strings
            array(0, '0'),
            array(0, false),
            array(1, true),
        );
    }

    public function considersUnequalProvider()
    {
        return array(
            // the first argument given here must be a valid array key
            //     = integers and non-integer strings
            array(0, ''),
            array('', 0),
            array('', false),
            array('', '0'),
        );
    }

    /**
     * @dataProvider considersEqualProvider
     */
    public function testIsChoiceConsidersEqual($choice, $otherChoice)
    {
        $this->renderer->setVar('choices', array(
            $choice => 'foo',
        ));

        $this->renderer->setVar('value', $choice);

        $this->assertTrue($this->renderer->isChoiceSelected($choice));
        $this->assertTrue($this->renderer->isChoiceSelected($otherChoice));
    }

    /**
     * @dataProvider considersUnequalProvider
     */
    public function testIsChoiceConsidersUnequal($choice, $otherChoice)
    {
        $this->renderer->setVar('choices', array(
            $choice => 'foo',
        ));

        $this->renderer->setVar('value', $choice);

        $this->assertTrue($this->renderer->isChoiceSelected($choice));
        $this->assertFalse($this->renderer->isChoiceSelected($otherChoice));
    }
}
