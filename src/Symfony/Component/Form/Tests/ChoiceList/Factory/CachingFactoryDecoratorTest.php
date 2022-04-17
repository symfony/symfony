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
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Tests\Fixtures\ArrayChoiceLoader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CachingFactoryDecoratorTest extends TestCase
{
    /**
     * @var MockObject&ChoiceListFactoryInterface
     */
    private $decoratedFactory;

    /**
     * @var CachingFactoryDecorator
     */
    private $factory;

    protected function setUp(): void
    {
        $this->decoratedFactory = $this->createMock(ChoiceListFactoryInterface::class);
        $this->factory = new CachingFactoryDecorator(new DefaultChoiceListFactory());
    }

    public function testCreateFromChoicesEmpty()
    {
        $list1 = $this->factory->createListFromChoices([]);
        $list2 = $this->factory->createListFromChoices([]);

        $this->assertSame($list1, $list2);
        $this->assertEquals(new ArrayChoiceList([]), $list1);
        $this->assertEquals(new ArrayChoiceList([]), $list2);
    }

    public function testCreateFromChoicesComparesTraversableChoicesAsArray()
    {
        // The top-most traversable is converted to an array
        $choices1 = new \ArrayIterator(['A' => 'a']);
        $choices2 = ['A' => 'a'];

        $list1 = $this->factory->createListFromChoices($choices1);
        $list2 = $this->factory->createListFromChoices($choices2);

        $this->assertSame($list1, $list2);
        $this->assertEquals(new ArrayChoiceList(['A' => 'a']), $list1);
        $this->assertEquals(new ArrayChoiceList(['A' => 'a']), $list2);
    }

    public function testCreateFromChoicesGroupedChoices()
    {
        $choices1 = ['key' => ['A' => 'a']];
        $choices2 = ['A' => 'a'];
        $list1 = $this->factory->createListFromChoices($choices1);
        $list2 = $this->factory->createListFromChoices($choices2);

        $this->assertNotSame($list1, $list2);
        $this->assertEquals(new ArrayChoiceList(['key' => ['A' => 'a']]), $list1);
        $this->assertEquals(new ArrayChoiceList(['A' => 'a']), $list2);
    }

    /**
     * @dataProvider provideSameChoices
     */
    public function testCreateFromChoicesSameChoices($choice1, $choice2)
    {
        $list1 = $this->factory->createListFromChoices([$choice1]);
        $list2 = $this->factory->createListFromChoices([$choice2]);

        $this->assertSame($list1, $list2);
        $this->assertEquals(new ArrayChoiceList([$choice1]), $list1);
        $this->assertEquals(new ArrayChoiceList([$choice2]), $list2);
    }

    /**
     * @dataProvider provideDistinguishedChoices
     */
    public function testCreateFromChoicesDifferentChoices($choice1, $choice2)
    {
        $list1 = $this->factory->createListFromChoices([$choice1]);
        $list2 = $this->factory->createListFromChoices([$choice2]);

        $this->assertNotSame($list1, $list2);
        $this->assertEquals(new ArrayChoiceList([$choice1]), $list1);
        $this->assertEquals(new ArrayChoiceList([$choice2]), $list2);
    }

    public function testCreateFromChoicesSameValueClosure()
    {
        $choices = [1];
        $closure = function () {};

        $list1 = $this->factory->createListFromChoices($choices, $closure);
        $list2 = $this->factory->createListFromChoices($choices, $closure);

        $this->assertNotSame($list1, $list2);
        $this->assertEquals(new ArrayChoiceList($choices, $closure), $list1);
        $this->assertEquals(new ArrayChoiceList($choices, $closure), $list2);
    }

    public function testCreateFromChoicesSameValueClosureUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $choices = [1];
        $list = new ArrayChoiceList([]);
        $formType = $this->createMock(FormTypeInterface::class);
        $valueCallback = function () {};

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $valueCallback)
            ->willReturn($list)
        ;

        $this->assertSame($list, $factory->createListFromChoices($choices, ChoiceList::value($formType, $valueCallback)));
        $this->assertSame($list, $factory->createListFromChoices($choices, ChoiceList::value($formType, function () {})));
    }

    public function testCreateFromChoicesDifferentValueClosure()
    {
        $choices = [1];
        $closure1 = function () {};
        $closure2 = function () {};
        $list1 = $this->factory->createListFromChoices($choices, $closure1);
        $list2 = $this->factory->createListFromChoices($choices, $closure2);

        $this->assertNotSame($list1, $list2);
        $this->assertEquals(new ArrayChoiceList($choices, $closure1), $list1);
        $this->assertEquals(new ArrayChoiceList($choices, $closure2), $list2);
    }

    public function testCreateFromChoicesSameFilterClosure()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $choices = [1];
        $list1 = new ArrayChoiceList([]);
        $list2 = new ArrayChoiceList([]);
        $filter = function () {};

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createListFromChoices')
            ->with($choices, null, $filter)
            ->willReturnOnConsecutiveCalls($list1, $list2);

        $this->assertSame($list1, $factory->createListFromChoices($choices, null, $filter));
        $this->assertSame($list2, $factory->createListFromChoices($choices, null, $filter));
    }

    public function testCreateFromChoicesSameFilterClosureUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $choices = [1];
        $list = new ArrayChoiceList([]);
        $formType = $this->createMock(FormTypeInterface::class);
        $filterCallback = function () {};

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, null, $filterCallback)
            ->willReturn($list)
        ;

        $this->assertSame($list, $factory->createListFromChoices($choices, null, ChoiceList::filter($formType, $filterCallback)));
        $this->assertSame($list, $factory->createListFromChoices($choices, null, ChoiceList::filter($formType, function () {})));
    }

    public function testCreateFromChoicesDifferentFilterClosure()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $choices = [1];
        $list1 = new ArrayChoiceList([]);
        $list2 = new ArrayChoiceList([]);
        $closure1 = function () {};
        $closure2 = function () {};

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createListFromChoices')
            ->withConsecutive(
                [$choices, null, $closure1],
                [$choices, null, $closure2]
            )
            ->willReturnOnConsecutiveCalls($list1, $list2);

        $this->assertSame($list1, $factory->createListFromChoices($choices, null, $closure1));
        $this->assertSame($list2, $factory->createListFromChoices($choices, null, $closure2));
    }

    public function testCreateFromLoaderSameLoader()
    {
        $loader = new ArrayChoiceLoader();
        $list1 = $this->factory->createListFromLoader($loader);
        $list2 = $this->factory->createListFromLoader($loader);

        $this->assertNotSame($list1, $list2);
        $this->assertEquals(new LazyChoiceList($loader), $list1);
        $this->assertEquals(new LazyChoiceList($loader), $list2);
    }

    public function testCreateFromLoaderSameLoaderUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $type = $this->createMock(FormTypeInterface::class);
        $loader = $this->createMock(ChoiceLoaderInterface::class);
        $list = new ArrayChoiceList([]);

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader)
            ->willReturn($list)
        ;

        $this->assertSame($list, $factory->createListFromLoader(ChoiceList::loader($type, $loader)));
        $this->assertSame($list, $factory->createListFromLoader(ChoiceList::loader($type, $this->createMock(ChoiceLoaderInterface::class))));
    }

    public function testCreateFromLoaderDifferentLoader()
    {
        $this->assertNotSame($this->factory->createListFromLoader(new ArrayChoiceLoader()), $this->factory->createListFromLoader(new ArrayChoiceLoader()));
    }

    public function testCreateFromLoaderSameValueClosure()
    {
        $loader = new ArrayChoiceLoader();
        $closure = function () {};
        $list1 = $this->factory->createListFromLoader($loader, $closure);
        $list2 = $this->factory->createListFromLoader($loader, $closure);

        $this->assertNotSame($list1, $list2);
        $this->assertEquals(new LazyChoiceList($loader, $closure), $list1);
        $this->assertEquals(new LazyChoiceList($loader, $closure), $list2);
    }

    public function testCreateFromLoaderSameValueClosureUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $type = $this->createMock(FormTypeInterface::class);
        $loader = $this->createMock(ChoiceLoaderInterface::class);
        $list = new ArrayChoiceList([]);
        $closure = function () {};

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, $closure)
            ->willReturn($list)
        ;

        $this->assertSame($list, $factory->createListFromLoader(
            ChoiceList::loader($type, $loader),
            ChoiceList::value($type, $closure)
        ));
        $this->assertSame($list, $factory->createListFromLoader(
            ChoiceList::loader($type, $this->createMock(ChoiceLoaderInterface::class)),
            ChoiceList::value($type, function () {})
        ));
    }

    public function testCreateFromLoaderDifferentValueClosure()
    {
        $loader = new ArrayChoiceLoader();
        $closure1 = function () {};
        $closure2 = function () {};

        $this->assertNotSame($this->factory->createListFromLoader($loader, $closure1), $this->factory->createListFromLoader($loader, $closure2));
    }

    public function testCreateFromLoaderSameFilterClosure()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $loader = $this->createMock(ChoiceLoaderInterface::class);
        $type = $this->createMock(FormTypeInterface::class);
        $list = new ArrayChoiceList([]);
        $list2 = new ArrayChoiceList([]);
        $closure = function () {};

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createListFromLoader')
            ->with($loader, null, $closure)
            ->willReturnOnConsecutiveCalls($list, $list2);

        $this->assertSame($list, $factory->createListFromLoader(ChoiceList::loader($type, $loader), null, $closure));
        $this->assertSame($list2, $factory->createListFromLoader(ChoiceList::loader($type, $this->createMock(ChoiceLoaderInterface::class)), null, $closure));
    }

    public function testCreateFromLoaderSameFilterClosureUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $type = $this->createMock(FormTypeInterface::class);
        $loader = $this->createMock(ChoiceLoaderInterface::class);
        $list = new ArrayChoiceList([]);
        $closure = function () {};

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, null, $closure)
            ->willReturn($list)
        ;

        $this->assertSame($list, $factory->createListFromLoader(
            ChoiceList::loader($type, $loader),
            null,
            ChoiceList::filter($type, $closure)
        ));
        $this->assertSame($list, $factory->createListFromLoader(
            ChoiceList::loader($type, $this->createMock(ChoiceLoaderInterface::class)),
            null,
            ChoiceList::filter($type, function () {})
        ));
    }

    public function testCreateFromLoaderDifferentFilterClosure()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $loader = $this->createMock(ChoiceLoaderInterface::class);
        $type = $this->createMock(FormTypeInterface::class);
        $list1 = new ArrayChoiceList([]);
        $list2 = new ArrayChoiceList([]);
        $closure1 = function () {};
        $closure2 = function () {};

        $this->decoratedFactory->expects($this->exactly(2))
            ->method('createListFromLoader')
            ->withConsecutive(
                [$loader, null, $closure1],
                [$loader, null, $closure2]
            )
            ->willReturnOnConsecutiveCalls($list1, $list2);

        $this->assertSame($list1, $factory->createListFromLoader(ChoiceList::loader($type, $loader), null, $closure1));
        $this->assertSame($list2, $factory->createListFromLoader(ChoiceList::loader($type, $this->createMock(ChoiceLoaderInterface::class)), null, $closure2));
    }

    public function testCreateViewSamePreferredChoices()
    {
        $preferred = ['a'];
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred);
        $view2 = $this->factory->createView($list, $preferred);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSamePreferredChoicesUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $preferred = ['a'];
        $type = $this->createMock(FormTypeInterface::class);
        $list = $this->createMock(ChoiceListInterface::class);
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $preferred)
            ->willReturn($view)
        ;

        $this->assertSame($view, $factory->createView($list, ChoiceList::preferred($type, $preferred)));
        $this->assertSame($view, $factory->createView($list, ChoiceList::preferred($type, ['a'])));
    }

    public function testCreateViewDifferentPreferredChoices()
    {
        $preferred1 = ['a'];
        $preferred2 = ['b'];
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred1);
        $view2 = $this->factory->createView($list, $preferred2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSamePreferredChoicesClosure()
    {
        $preferred = function () {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred);
        $view2 = $this->factory->createView($list, $preferred);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSamePreferredChoicesClosureUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $preferredCallback = function () {};
        $type = $this->createMock(FormTypeInterface::class);
        $list = $this->createMock(ChoiceListInterface::class);
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $preferredCallback)
            ->willReturn($view)
        ;

        $this->assertSame($view, $factory->createView($list, ChoiceList::preferred($type, $preferredCallback)));
        $this->assertSame($view, $factory->createView($list, ChoiceList::preferred($type, function () {})));
    }

    public function testCreateViewDifferentPreferredChoicesClosure()
    {
        $preferred1 = function () {};
        $preferred2 = function () {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred1);
        $view2 = $this->factory->createView($list, $preferred2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameLabelClosure()
    {
        $labels = function () {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, $labels);
        $view2 = $this->factory->createView($list, null, $labels);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameLabelClosureUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $labelsCallback = function () {};
        $type = $this->createMock(FormTypeInterface::class);
        $list = $this->createMock(ChoiceListInterface::class);
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, $labelsCallback)
            ->willReturn($view)
        ;

        $this->assertSame($view, $factory->createView($list, null, ChoiceList::label($type, $labelsCallback)));
        $this->assertSame($view, $factory->createView($list, null, ChoiceList::label($type, function () {})));
    }

    public function testCreateViewDifferentLabelClosure()
    {
        $labels1 = function () {};
        $labels2 = function () {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, $labels1);
        $view2 = $this->factory->createView($list, null, $labels2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameIndexClosure()
    {
        $index = function () {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, $index);
        $view2 = $this->factory->createView($list, null, null, $index);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameIndexClosureUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $indexCallback = function () {};
        $type = $this->createMock(FormTypeInterface::class);
        $list = $this->createMock(ChoiceListInterface::class);
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, $indexCallback)
            ->willReturn($view)
        ;

        $this->assertSame($view, $factory->createView($list, null, null, ChoiceList::fieldName($type, $indexCallback)));
        $this->assertSame($view, $factory->createView($list, null, null, ChoiceList::fieldName($type, function () {})));
    }

    public function testCreateViewDifferentIndexClosure()
    {
        $index1 = function () {};
        $index2 = function () {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, $index1);
        $view2 = $this->factory->createView($list, null, null, $index2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameGroupByClosure()
    {
        $groupBy = function () {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, $groupBy);
        $view2 = $this->factory->createView($list, null, null, null, $groupBy);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameGroupByClosureUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $groupByCallback = function () {};
        $type = $this->createMock(FormTypeInterface::class);
        $list = $this->createMock(ChoiceListInterface::class);
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, $groupByCallback)
            ->willReturn($view)
        ;

        $this->assertSame($view, $factory->createView($list, null, null, null, ChoiceList::groupBy($type, $groupByCallback)));
        $this->assertSame($view, $factory->createView($list, null, null, null, ChoiceList::groupBy($type, function () {})));
    }

    public function testCreateViewDifferentGroupByClosure()
    {
        $groupBy1 = function () {};
        $groupBy2 = function () {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, $groupBy1);
        $view2 = $this->factory->createView($list, null, null, null, $groupBy2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameAttributes()
    {
        $attr = ['class' => 'foobar'];
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, null, $attr);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameAttributesUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $attr = ['class' => 'foobar'];
        $type = $this->createMock(FormTypeInterface::class);
        $list = $this->createMock(ChoiceListInterface::class);
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, $attr)
            ->willReturn($view);

        $this->assertSame($view, $factory->createView($list, null, null, null, null, ChoiceList::attr($type, $attr)));
        $this->assertSame($view, $factory->createView($list, null, null, null, null, ChoiceList::attr($type, ['class' => 'foobar'])));
    }

    public function testCreateViewDifferentAttributes()
    {
        $attr1 = ['class' => 'foobar1'];
        $attr2 = ['class' => 'foobar2'];
        $list = new ArrayChoiceList([]);

        $view1 = $this->factory->createView($list, null, null, null, null, $attr1);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameAttributesClosure()
    {
        $attr = function () {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, null, $attr);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameAttributesClosureUseCache()
    {
        $factory = new CachingFactoryDecorator($this->decoratedFactory);
        $attrCallback = function () {};
        $type = $this->createMock(FormTypeInterface::class);
        $list = $this->createMock(ChoiceListInterface::class);
        $view = new ChoiceListView();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, $attrCallback)
            ->willReturn($view);

        $this->assertSame($view, $factory->createView($list, null, null, null, null, ChoiceList::attr($type, $attrCallback)));
        $this->assertSame($view, $factory->createView($list, null, null, null, null, ChoiceList::attr($type, function () {})));
    }

    public function testCreateViewDifferentAttributesClosure()
    {
        $attr1 = function () {};
        $attr2 = function () {};
        $list = new ArrayChoiceList([]);

        $view1 = $this->factory->createView($list, null, null, null, null, $attr1);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
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
