<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

/**
 * @author Barthold Bos <bartholdbos@hotmail.com>
 */
class FormViewTest extends TestCase
{
    /**
     * @var FormView
     */
    private $formView;

    protected function setUp()
    {
        $this->formView = new FormView();
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testOffsetSet()
    {
        $this->formView->offsetSet('', '');
    }

    public function testGeneric()
    {
        $this->assertFalse($this->formView->isMethodRendered());

        $this->formView->setMethodRendered();

        $this->assertTrue($this->formView->isMethodRendered());
        $this->assertFalse($this->formView->isRendered());

        $child1 = new FormView();
        $this->formView->children['foo'] = $child1;

        $this->assertTrue($this->formView->offsetExists('foo'));
        $this->assertTrue(isset($this->formView['foo']));
        $this->assertEquals($child1, $this->formView->offsetGet('foo'));
        $this->assertEquals($child1, $this->formView['foo']);
        $this->assertCount(1, $this->formView);
        $this->assertEquals($this->formView->children, iterator_to_array($this->formView->getIterator()));
        $this->assertFalse($this->formView->isRendered());

        $this->formView->children['foo']->setRendered();

        $this->assertTrue($this->formView->isRendered());

        $this->formView->offsetUnset('foo');

        $this->assertFalse(isset($this->formView['foo']));
    }
}
