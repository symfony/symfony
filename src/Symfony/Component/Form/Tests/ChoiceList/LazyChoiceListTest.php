<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList;

use Symfony\Component\Form\ChoiceList\LazyChoiceList;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LazyChoiceListTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LazyChoiceList
     */
    private $list;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $innerList;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $loader;

    private $value;

    protected function setUp()
    {
        $this->innerList = $this->getMock('Symfony\Component\Form\ChoiceList\ChoiceListInterface');
        $this->loader = $this->getMock('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface');
        $this->value = function () {};
        $this->list = new LazyChoiceList($this->loader, $this->value);
    }

    public function testGetChoicesLoadsInnerListOnFirstCall()
    {
        $this->loader->expects($this->once())
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->innerList));

        $this->innerList->expects($this->exactly(2))
            ->method('getChoices')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getChoices());
        $this->assertSame('RESULT', $this->list->getChoices());
    }

    public function testGetValuesLoadsInnerListOnFirstCall()
    {
        $this->loader->expects($this->once())
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->innerList));

        $this->innerList->expects($this->exactly(2))
            ->method('getValues')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getValues());
        $this->assertSame('RESULT', $this->list->getValues());
    }

    public function testGetStructuredValuesLoadsInnerListOnFirstCall()
    {
        $this->loader->expects($this->once())
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->innerList));

        $this->innerList->expects($this->exactly(2))
            ->method('getStructuredValues')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getStructuredValues());
        $this->assertSame('RESULT', $this->list->getStructuredValues());
    }

    public function testGetOriginalKeysLoadsInnerListOnFirstCall()
    {
        $this->loader->expects($this->once())
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->innerList));

        $this->innerList->expects($this->exactly(2))
            ->method('getOriginalKeys')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getOriginalKeys());
        $this->assertSame('RESULT', $this->list->getOriginalKeys());
    }

    public function testGetChoicesForValuesForwardsCallIfListNotLoaded()
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoicesForValues')
            ->with(array('a', 'b'))
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getChoicesForValues(array('a', 'b')));
        $this->assertSame('RESULT', $this->list->getChoicesForValues(array('a', 'b')));
    }

    public function testGetChoicesForValuesUsesLoadedList()
    {
        $this->loader->expects($this->once())
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->innerList));

        $this->loader->expects($this->never())
            ->method('loadChoicesForValues');

        $this->innerList->expects($this->exactly(2))
            ->method('getChoicesForValues')
            ->with(array('a', 'b'))
            ->will($this->returnValue('RESULT'));

        // load choice list
        $this->list->getChoices();

        $this->assertSame('RESULT', $this->list->getChoicesForValues(array('a', 'b')));
        $this->assertSame('RESULT', $this->list->getChoicesForValues(array('a', 'b')));
    }

    public function testGetValuesForChoicesForwardsCallIfListNotLoaded()
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadValuesForChoices')
            ->with(array('a', 'b'))
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getValuesForChoices(array('a', 'b')));
        $this->assertSame('RESULT', $this->list->getValuesForChoices(array('a', 'b')));
    }

    public function testGetValuesForChoicesUsesLoadedList()
    {
        $this->loader->expects($this->once())
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->innerList));

        $this->loader->expects($this->never())
            ->method('loadValuesForChoices');

        $this->innerList->expects($this->exactly(2))
            ->method('getValuesForChoices')
            ->with(array('a', 'b'))
            ->will($this->returnValue('RESULT'));

        // load choice list
        $this->list->getChoices();

        $this->assertSame('RESULT', $this->list->getValuesForChoices(array('a', 'b')));
        $this->assertSame('RESULT', $this->list->getValuesForChoices(array('a', 'b')));
    }
}
