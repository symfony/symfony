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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LazyChoiceListTest extends TestCase
{
    /**
     * @var LazyChoiceList
     */
    private $list;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $loadedList;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $loader;

    private $value;

    protected function setUp(): void
    {
        $this->loadedList = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $this->loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $this->value = function (): void {};
        $this->list = new LazyChoiceList($this->loader, $this->value);
    }

    public function testGetChoiceLoadersLoadsLoadedListOnFirstCall(): void
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->loadedList));

        // The same list is returned by the loader
        $this->loadedList->expects($this->exactly(2))
            ->method('getChoices')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getChoices());
        $this->assertSame('RESULT', $this->list->getChoices());
    }

    public function testGetValuesLoadsLoadedListOnFirstCall(): void
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->loadedList));

        // The same list is returned by the loader
        $this->loadedList->expects($this->exactly(2))
            ->method('getValues')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getValues());
        $this->assertSame('RESULT', $this->list->getValues());
    }

    public function testGetStructuredValuesLoadsLoadedListOnFirstCall(): void
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->loadedList));

        // The same list is returned by the loader
        $this->loadedList->expects($this->exactly(2))
            ->method('getStructuredValues')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getStructuredValues());
        $this->assertSame('RESULT', $this->list->getStructuredValues());
    }

    public function testGetOriginalKeysLoadsLoadedListOnFirstCall(): void
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->loadedList));

        // The same list is returned by the loader
        $this->loadedList->expects($this->exactly(2))
            ->method('getOriginalKeys')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getOriginalKeys());
        $this->assertSame('RESULT', $this->list->getOriginalKeys());
    }

    public function testGetChoicesForValuesForwardsCallIfListNotLoaded(): void
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoicesForValues')
            ->with(array('a', 'b'))
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->list->getChoicesForValues(array('a', 'b')));
        $this->assertSame('RESULT', $this->list->getChoicesForValues(array('a', 'b')));
    }

    public function testGetChoicesForValuesUsesLoadedList(): void
    {
        $this->loader->expects($this->exactly(1))
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->loadedList));

        $this->loader->expects($this->exactly(2))
            ->method('loadChoicesForValues')
            ->with(array('a', 'b'))
            ->will($this->returnValue('RESULT'));

        // load choice list
        $this->list->getChoices();

        $this->assertSame('RESULT', $this->list->getChoicesForValues(array('a', 'b')));
        $this->assertSame('RESULT', $this->list->getChoicesForValues(array('a', 'b')));
    }

    public function testGetValuesForChoicesUsesLoadedList(): void
    {
        $this->loader->expects($this->exactly(1))
            ->method('loadChoiceList')
            ->with($this->value)
            ->will($this->returnValue($this->loadedList));

        $this->loader->expects($this->exactly(2))
            ->method('loadValuesForChoices')
            ->with(array('a', 'b'))
            ->will($this->returnValue('RESULT'));

        // load choice list
        $this->list->getChoices();

        $this->assertSame('RESULT', $this->list->getValuesForChoices(array('a', 'b')));
        $this->assertSame('RESULT', $this->list->getValuesForChoices(array('a', 'b')));
    }
}
