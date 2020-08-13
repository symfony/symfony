<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList\Factory;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CachingFactoryDecoratorTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $decoratedFactory;

    /**
     * @var CachingFactoryDecorator
     */
    private $factory;

    protected function setUp()
    {
        $this->decoratedFactory = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface')->getMock();
        $this->factory = new CachingFactoryDecorator($this->decoratedFactory);
    }

    public function testCreateFromChoicesEmpty()
    {
        $list = new ArrayChoiceList([]);

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with([])
            ->willReturn($list);

        $this->assertSame($list, $this->factory->createListFromChoices([]));
        $this->assertSame($list, $this->factory->createListFromChoices([]));
    }

    public function testCreateFromChoicesComparesTraversableChoicesAsArray()
    {
        // The top-most traversable is converted to an array
        $choices1 = new \ArrayIterator(['A' => 'a']);
        $choices2 = ['A' => 'a'];
        $list = new ArrayChoiceList([]);

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices2)
            ->willReturn($list);

        $this->assertSame($list, $this->factory->createListFromChoices($choices1));
        $this->assertSame($list, $this->factory->createListFromChoices($choices2));
    }

    public function testCreateFromChoicesGroupedChoices()
    {
        $choices1 = ['key' => ['A' => 'a']];
        $choices2 = ['A' => 'a'];
        $list1 = new ArrayChoiceList([]);
        $list2 = new ArrayChoiceList([]);

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createListFromChoices')
            ->withConsecutive(
                [$choices1],
                [$choices2]
            )
            ->willReturnOnConsecutiveCalls($list1, $list2);

        $this->assertSame($list1, $this->factory->createListFromChoices($choices1));
        $this->assertSame($list2, $this->factory->createListFromChoices($choices2));
    }

    /**
     * @dataProvider provideSameChoices
     */
    public function testCreateFromChoicesSameChoices($choice1, $choice2)
    {
        $choices1 = [$choice1];
        $choices2 = [$choice2];
        $list = new ArrayChoiceList([]);

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices1)
            ->willReturn($list);

        $this->assertSame($list, $this->factory->createListFromChoices($choices1));
        $this->assertSame($list, $this->factory->createListFromChoices($choices2));
    }

    /**
     * @dataProvider provideDistinguishedChoices
     */
    public function testCreateFromChoicesDifferentChoices($choice1, $choice2)
    {
        $choices1 = [$choice1];
        $choices2 = [$choice2];
        $list1 = new ArrayChoiceList([]);
        $list2 = new ArrayChoiceList([]);

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createListFromChoices')
            ->withConsecutive(
                [$choices1],
                [$choices2]
            )
            ->willReturnOnConsecutiveCalls($list1, $list2);

        $this->assertSame($list1, $this->factory->createListFromChoices($choices1));
        $this->assertSame($list2, $this->factory->createListFromChoices($choices2));
    }

    public function testCreateFromChoicesSameValueClosure()
    {
        $choices = [1];
        $list = new ArrayChoiceList([]);
        $closure = function () {};

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $closure)
            ->willReturn($list);

        $this->assertSame($list, $this->factory->createListFromChoices($choices, $closure));
        $this->assertSame($list, $this->factory->createListFromChoices($choices, $closure));
    }

    public function testCreateFromChoicesDifferentValueClosure()
    {
        $choices = [1];
        $list1 = new ArrayChoiceList([]);
        $list2 = new ArrayChoiceList([]);
        $closure1 = function () {};
        $closure2 = function () {};

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createListFromChoices')
            ->withConsecutive(
                [$choices, $closure1],
                [$choices, $closure2]
            )
            ->willReturnOnConsecutiveCalls($list1, $list2);

        $this->assertSame($list1, $this->factory->createListFromChoices($choices, $closure1));
        $this->assertSame($list2, $this->factory->createListFromChoices($choices, $closure2));
    }

    public function testCreateFromLoaderSameLoader()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $list = new ArrayChoiceList([]);

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader)
            ->willReturn($list);

        $this->assertSame($list, $this->factory->createListFromLoader($loader));
        $this->assertSame($list, $this->factory->createListFromLoader($loader));
    }

    public function testCreateFromLoaderDifferentLoader()
    {
        $loader1 = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $loader2 = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $list1 = new ArrayChoiceList([]);
        $list2 = new ArrayChoiceList([]);

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createListFromLoader')
            ->withConsecutive(
                [$loader1],
                [$loader2]
            )
            ->willReturnOnConsecutiveCalls($list1, $list2);

        $this->assertSame($list1, $this->factory->createListFromLoader($loader1));
        $this->assertSame($list2, $this->factory->createListFromLoader($loader2));
    }

    public function testCreateFromLoaderSameValueClosure()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $list = new ArrayChoiceList([]);
        $closure = function () {};

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, $closure)
            ->willReturn($list);

        $this->assertSame($list, $this->factory->createListFromLoader($loader, $closure));
        $this->assertSame($list, $this->factory->createListFromLoader($loader, $closure));
    }

    public function testCreateFromLoaderDifferentValueClosure()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $list1 = new ArrayChoiceList([]);
        $list2 = new ArrayChoiceList([]);
        $closure1 = function () {};
        $closure2 = function () {};

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createListFromLoader')
            ->withConsecutive(
                [$loader, $closure1],
                [$loader, $closure2]
            )
            ->willReturnOnConsecutiveCalls($list1, $list2);

        $this->assertSame($list1, $this->factory->createListFromLoader($loader, $closure1));
        $this->assertSame($list2, $this->factory->createListFromLoader($loader, $closure2));
    }

    public function testCreateViewSamePreferredChoices()
    {
        $preferred = ['a'];
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $preferred)
            ->willReturn($view);

        $this->assertSame($view, $this->factory->createView($list, $preferred));
        $this->assertSame($view, $this->factory->createView($list, $preferred));
    }

    public function testCreateViewDifferentPreferredChoices()
    {
        $preferred1 = ['a'];
        $preferred2 = ['b'];
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new ChoiceListView();
        $view2 = new ChoiceListView();

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createView')
            ->withConsecutive(
                [$list, $preferred1],
                [$list, $preferred2]
            )
            ->willReturnOnConsecutiveCalls($view1, $view2);

        $this->assertSame($view1, $this->factory->createView($list, $preferred1));
        $this->assertSame($view2, $this->factory->createView($list, $preferred2));
    }

    public function testCreateViewSamePreferredChoicesClosure()
    {
        $preferred = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $preferred)
            ->willReturn($view);

        $this->assertSame($view, $this->factory->createView($list, $preferred));
        $this->assertSame($view, $this->factory->createView($list, $preferred));
    }

    public function testCreateViewDifferentPreferredChoicesClosure()
    {
        $preferred1 = function () {};
        $preferred2 = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new ChoiceListView();
        $view2 = new ChoiceListView();

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createView')
            ->withConsecutive(
                [$list, $preferred1],
                [$list, $preferred2]
            )
            ->willReturnOnConsecutiveCalls($view1, $view2);

        $this->assertSame($view1, $this->factory->createView($list, $preferred1));
        $this->assertSame($view2, $this->factory->createView($list, $preferred2));
    }

    public function testCreateViewSameLabelClosure()
    {
        $labels = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, $labels)
            ->willReturn($view);

        $this->assertSame($view, $this->factory->createView($list, null, $labels));
        $this->assertSame($view, $this->factory->createView($list, null, $labels));
    }

    public function testCreateViewDifferentLabelClosure()
    {
        $labels1 = function () {};
        $labels2 = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new ChoiceListView();
        $view2 = new ChoiceListView();

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createView')
            ->withConsecutive(
                [$list, null, $labels1],
                [$list, null, $labels2]
            )
            ->willReturnOnConsecutiveCalls($view1, $view2);

        $this->assertSame($view1, $this->factory->createView($list, null, $labels1));
        $this->assertSame($view2, $this->factory->createView($list, null, $labels2));
    }

    public function testCreateViewSameIndexClosure()
    {
        $index = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, $index)
            ->willReturn($view);

        $this->assertSame($view, $this->factory->createView($list, null, null, $index));
        $this->assertSame($view, $this->factory->createView($list, null, null, $index));
    }

    public function testCreateViewDifferentIndexClosure()
    {
        $index1 = function () {};
        $index2 = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new ChoiceListView();
        $view2 = new ChoiceListView();

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createView')
            ->withConsecutive(
                [$list, null, null, $index1],
                [$list, null, null, $index2]
            )
            ->willReturnOnConsecutiveCalls($view1, $view2);

        $this->assertSame($view1, $this->factory->createView($list, null, null, $index1));
        $this->assertSame($view2, $this->factory->createView($list, null, null, $index2));
    }

    public function testCreateViewSameGroupByClosure()
    {
        $groupBy = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, $groupBy)
            ->willReturn($view);

        $this->assertSame($view, $this->factory->createView($list, null, null, null, $groupBy));
        $this->assertSame($view, $this->factory->createView($list, null, null, null, $groupBy));
    }

    public function testCreateViewDifferentGroupByClosure()
    {
        $groupBy1 = function () {};
        $groupBy2 = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new ChoiceListView();
        $view2 = new ChoiceListView();

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createView')
            ->withConsecutive(
                [$list, null, null, null, $groupBy1],
                [$list, null, null, null, $groupBy2]
            )
            ->willReturnOnConsecutiveCalls($view1, $view2);

        $this->assertSame($view1, $this->factory->createView($list, null, null, null, $groupBy1));
        $this->assertSame($view2, $this->factory->createView($list, null, null, null, $groupBy2));
    }

    public function testCreateViewSameAttributes()
    {
        $attr = ['class' => 'foobar'];
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, $attr)
            ->willReturn($view);

        $this->assertSame($view, $this->factory->createView($list, null, null, null, null, $attr));
        $this->assertSame($view, $this->factory->createView($list, null, null, null, null, $attr));
    }

    public function testCreateViewDifferentAttributes()
    {
        $attr1 = ['class' => 'foobar1'];
        $attr2 = ['class' => 'foobar2'];
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new ChoiceListView();
        $view2 = new ChoiceListView();

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createView')
            ->withConsecutive(
                [$list, null, null, null, null, $attr1],
                [$list, null, null, null, null, $attr2]
            )
            ->willReturnOnConsecutiveCalls($view1, $view2);

        $this->assertSame($view1, $this->factory->createView($list, null, null, null, null, $attr1));
        $this->assertSame($view2, $this->factory->createView($list, null, null, null, null, $attr2));
    }

    public function testCreateViewSameAttributesClosure()
    {
        $attr = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, $attr)
            ->willReturn($view);

        $this->assertSame($view, $this->factory->createView($list, null, null, null, null, $attr));
        $this->assertSame($view, $this->factory->createView($list, null, null, null, null, $attr));
    }

    public function testCreateViewDifferentAttributesClosure()
    {
        $attr1 = function () {};
        $attr2 = function () {};
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $view1 = new ChoiceListView();
        $view2 = new ChoiceListView();

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createView')
            ->withConsecutive(
                [$list, null, null, null, null, $attr1],
                [$list, null, null, null, null, $attr2]
            )
            ->willReturnOnConsecutiveCalls($view1, $view2);

        $this->assertSame($view1, $this->factory->createView($list, null, null, null, null, $attr1));
        $this->assertSame($view2, $this->factory->createView($list, null, null, null, null, $attr2));
    }

    public function provideSameChoices()
    {
        $object = (object) ['foo' => 'bar'];

        return [
            [0, 0],
            ['a', 'a'],
            // https://github.com/symfony/symfony/issues/10409
            [\chr(181).'meter', \chr(181).'meter'], // UTF-8
            [$object, $object],
        ];
    }

    public function provideDistinguishedChoices()
    {
        return [
            [0, false],
            [0, null],
            [0, '0'],
            [0, ''],
            [1, true],
            [1, '1'],
            [1, 'a'],
            ['', false],
            ['', null],
            [false, null],
            // Same properties, but not identical
            [(object) ['foo' => 'bar'], (object) ['foo' => 'bar']],
        ];
    }

    public function provideSameKeyChoices()
    {
        // Only test types here that can be used as array keys
        return [
            [0, 0],
            [0, '0'],
            ['a', 'a'],
            [\chr(181).'meter', \chr(181).'meter'],
        ];
    }

    public function provideDistinguishedKeyChoices()
    {
        // Only test types here that can be used as array keys
        return [
            [0, ''],
            [1, 'a'],
            ['', 'a'],
        ];
    }
}
