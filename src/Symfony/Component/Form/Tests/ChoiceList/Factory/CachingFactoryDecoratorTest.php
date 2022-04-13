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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\Tests\Fixtures\ArrayChoiceLoader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CachingFactoryDecoratorTest extends TestCase
{
    /**
     * @var CachingFactoryDecorator
     */
    private $factory;

    protected function setUp(): void
    {
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

        $this->assertSame($list1, $list2);
        $this->assertEquals(new ArrayChoiceList($choices, $closure), $list1);
        $this->assertEquals(new ArrayChoiceList($choices, $closure), $list2);
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

    public function testCreateFromLoaderSameLoader()
    {
        $loader = new ArrayChoiceLoader();

        $this->assertSame($this->factory->createListFromLoader($loader), $this->factory->createListFromLoader($loader));
    }

    public function testCreateFromLoaderDifferentLoader()
    {
        $this->assertNotSame($this->factory->createListFromLoader(new ArrayChoiceLoader()), $this->factory->createListFromLoader(new ArrayChoiceLoader()));
    }

    public function testCreateFromLoaderSameValueClosure()
    {
        $loader = new ArrayChoiceLoader();
        $closure = function () {};

        $this->assertSame($this->factory->createListFromLoader($loader, $closure), $this->factory->createListFromLoader($loader, $closure));
    }

    public function testCreateFromLoaderDifferentValueClosure()
    {
        $loader = new ArrayChoiceLoader();
        $closure1 = function () {};
        $closure2 = function () {};

        $this->assertNotSame($this->factory->createListFromLoader($loader, $closure1), $this->factory->createListFromLoader($loader, $closure2));
    }

    public function testCreateViewSamePreferredChoices()
    {
        $preferred = ['a'];
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred);
        $view2 = $this->factory->createView($list, $preferred);

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
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

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
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

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
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

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
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

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
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

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
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

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
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
