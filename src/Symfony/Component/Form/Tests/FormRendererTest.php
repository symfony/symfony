<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

class FormRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $engine;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $csrfProvider;

    /**
     * @var FormRenderer
     */
    private $renderer;

    protected function setUp()
    {
        $this->engine = $this->getMock('Symfony\Component\Form\FormRendererEngineInterface');
        $this->csrfProvider = $this->getMock('Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface');
        $this->renderer = new FormRenderer($this->engine, $this->csrfProvider);
    }

    public function isChoiceGroupProvider()
    {
        return array(
            array(false, 0),
            array(false, '0'),
            array(false, '1'),
            array(false, 1),
            array(false, ''),
            array(false, null),
            array(false, true),

            array(true, array()),
        );
    }

    /**
     * @dataProvider isChoiceGroupProvider
     */
    public function testIsChoiceGroup($expected, $value)
    {
        $this->assertSame($expected, $this->renderer->isChoiceGroup($value));
    }

    public function testIsChoiceGroupPart2()
    {
        $this->assertTrue($this->renderer->isChoiceGroup(new \SplFixedArray(1)));
    }

    public function isChoiceSelectedProvider()
    {
        // The commented cases should not be necessary anymore, because the
        // choice lists should assure that both values passed here are always
        // strings
        return array(
//             array(true, 0, 0),
            array(true, '0', '0'),
            array(true, '1', '1'),
//             array(true, false, 0),
//             array(true, true, 1),
            array(true, '', ''),
//             array(true, null, ''),
            array(true, '1.23', '1.23'),
            array(true, 'foo', 'foo'),
            array(true, 'foo10', 'foo10'),
            array(true, 'foo', array(1, 'foo', 'foo10')),

            array(false, 10, array(1, 'foo', 'foo10')),
            array(false, 0, array(1, 'foo', 'foo10')),
        );
    }

    /**
     * @dataProvider isChoiceSelectedProvider
     */
    public function testIsChoiceSelected($expected, $choice, $value)
    {
        $view = new FormView();
        $view->vars['value'] = $value;
        $choice = new ChoiceView($choice, $choice . ' label');

        $this->assertSame($expected, $this->renderer->isChoiceSelected($view, $choice));
    }
}
