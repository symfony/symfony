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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\ChoiceList\Loader\FilterChoiceLoaderDecorator;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Tests\ChoiceList\ChoiceListAssertionTrait;
use Symfony\Component\Form\Tests\Fixtures\ArrayChoiceLoader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CachingFactoryDecoratorTest extends TestCase
{
    use ChoiceListAssertionTrait;

    private CachingFactoryDecorator $factory;

    protected function setUp(): void
    {
        $this->factory = new CachingFactoryDecorator(new DefaultChoiceListFactory());
    }

    public function testCreateFromChoicesEmpty(): void
    {
        $list1 = $this->factory->createListFromChoices([]);
        $list2 = $this->factory->createListFromChoices([]);

        $this->assertSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([]), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([]), $list2);
    }

    public function testCreateFromChoicesComparesTraversableChoicesAsArray(): void
    {
        // The top-most traversable is converted to an array
        $choices1 = new \ArrayIterator(['A' => 'a']);
        $choices2 = ['A' => 'a'];

        $list1 = $this->factory->createListFromChoices($choices1);
        $list2 = $this->factory->createListFromChoices($choices2);

        $this->assertSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList(['A' => 'a']), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList(['A' => 'a']), $list2);
    }

    public function testCreateFromChoicesGroupedChoices(): void
    {
        $choices1 = ['key' => ['A' => 'a']];
        $choices2 = ['A' => 'a'];
        $list1 = $this->factory->createListFromChoices($choices1);
        $list2 = $this->factory->createListFromChoices($choices2);

        $this->assertNotSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList(['key' => ['A' => 'a']]), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList(['A' => 'a']), $list2);
    }

    #[DataProvider('provideSameChoices')]
    public function testCreateFromChoicesSameChoices($choice1, $choice2): void
    {
        $list1 = $this->factory->createListFromChoices([$choice1]);
        $list2 = $this->factory->createListFromChoices([$choice2]);

        $this->assertSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([$choice1]), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([$choice2]), $list2);
    }

    #[DataProvider('provideDistinguishedChoices')]
    public function testCreateFromChoicesDifferentChoices($choice1, $choice2): void
    {
        $list1 = $this->factory->createListFromChoices([$choice1]);
        $list2 = $this->factory->createListFromChoices([$choice2]);

        $this->assertNotSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([$choice1]), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([$choice2]), $list2);
    }

    public function testCreateFromChoicesSameValueClosure(): void
    {
        $choices = [1];
        $closure = static function (): void {};

        $list1 = $this->factory->createListFromChoices($choices, $closure);
        $list2 = $this->factory->createListFromChoices($choices, $closure);

        $this->assertNotSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList($choices, $closure), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList($choices, $closure), $list2);
    }

    public function testCreateFromChoicesSameValueClosureUseCache(): void
    {
        $choices = [1];
        $formType = new FormType();
        $valueCallback = static function (): void {};

        $list1 = $this->factory->createListFromChoices($choices, ChoiceList::value($formType, $valueCallback));
        $list2 = $this->factory->createListFromChoices($choices, ChoiceList::value($formType, static function (): void {}));

        $this->assertSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList($choices, $valueCallback), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList($choices, static function (): void {}), $list2);
    }

    public function testCreateFromChoicesDifferentValueClosure(): void
    {
        $choices = [1];
        $closure1 = static function (): void {};
        $closure2 = static function (): void {};
        $list1 = $this->factory->createListFromChoices($choices, $closure1);
        $list2 = $this->factory->createListFromChoices($choices, $closure2);

        $this->assertNotSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList($choices, $closure1), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList($choices, $closure2), $list2);
    }

    public function testCreateFromChoicesSameFilterClosure(): void
    {
        $choices = [1];
        $filter = static function (): void {};
        $list1 = $this->factory->createListFromChoices($choices, null, $filter);
        $list2 = $this->factory->createListFromChoices($choices, null, $filter);
        $lazyChoiceList = new LazyChoiceList(new FilterChoiceLoaderDecorator(new CallbackChoiceLoader(static fn () => $choices), $filter), null);

        $this->assertNotSame($list1, $list2);
        self::assertEqualsLazyChoiceList($lazyChoiceList, $list1);
        self::assertEqualsLazyChoiceList($lazyChoiceList, $list2);
    }

    public function testCreateFromChoicesSameFilterClosureUseCache(): void
    {
        $choices = [1];
        $formType = new FormType();
        $filterCallback = static function (): void {};
        $list1 = $this->factory->createListFromChoices($choices, null, ChoiceList::filter($formType, $filterCallback));
        $list2 = $this->factory->createListFromChoices($choices, null, ChoiceList::filter($formType, static function (): void {}));
        $lazyChoiceList = new LazyChoiceList(new FilterChoiceLoaderDecorator(new CallbackChoiceLoader(static fn () => $choices), static function (): void {}), null);

        $this->assertSame($list1, $list2);
        self::assertEqualsLazyChoiceList($lazyChoiceList, $list1);
        self::assertEqualsLazyChoiceList($lazyChoiceList, $list2);
    }

    public function testCreateFromChoicesDifferentFilterClosure(): void
    {
        $choices = [1];
        $closure1 = static function (): void {};
        $closure2 = static function (): void {};
        $list1 = $this->factory->createListFromChoices($choices, null, $closure1);
        $list2 = $this->factory->createListFromChoices($choices, null, $closure2);
        $lazyChoiceList = new LazyChoiceList(new FilterChoiceLoaderDecorator(new CallbackChoiceLoader(static fn () => $choices), static function (): void {}), null);

        $this->assertNotSame($list1, $list2);
        self::assertEqualsLazyChoiceList($lazyChoiceList, $list1);
        self::assertEqualsLazyChoiceList($lazyChoiceList, $list2);
    }

    public function testCreateFromLoaderSameLoader(): void
    {
        $loader = new ArrayChoiceLoader();
        $list1 = $this->factory->createListFromLoader($loader);
        $list2 = $this->factory->createListFromLoader($loader);

        $this->assertNotSame($list1, $list2);
        self::assertEqualsLazyChoiceList(new LazyChoiceList($loader), $list1);
        self::assertEqualsLazyChoiceList(new LazyChoiceList($loader), $list2);
    }

    public function testCreateFromLoaderSameLoaderUseCache(): void
    {
        $type = new FormType();
        $list1 = $this->factory->createListFromLoader(ChoiceList::loader($type, new ArrayChoiceLoader()));
        $list2 = $this->factory->createListFromLoader(ChoiceList::loader($type, new ArrayChoiceLoader()));

        $this->assertSame($list1, $list2);
        self::assertEqualsLazyChoiceList(new LazyChoiceList(new ArrayChoiceLoader(), null), $list1);
        self::assertEqualsLazyChoiceList(new LazyChoiceList(new ArrayChoiceLoader(), null), $list2);
    }

    public function testCreateFromLoaderDifferentLoader(): void
    {
        $this->assertNotSame($this->factory->createListFromLoader(new ArrayChoiceLoader()), $this->factory->createListFromLoader(new ArrayChoiceLoader()));
    }

    public function testCreateFromLoaderSameValueClosure(): void
    {
        $loader = new ArrayChoiceLoader();
        $closure = static function (): void {};
        $list1 = $this->factory->createListFromLoader($loader, $closure);
        $list2 = $this->factory->createListFromLoader($loader, $closure);

        $this->assertNotSame($list1, $list2);
        self::assertEqualsLazyChoiceList(new LazyChoiceList($loader, $closure), $list1);
        self::assertEqualsLazyChoiceList(new LazyChoiceList($loader, $closure), $list2);
    }

    public function testCreateFromLoaderSameValueClosureUseCache(): void
    {
        $type = new FormType();
        $loader = new ArrayChoiceLoader();
        $closure = static function (): void {};
        $list1 = $this->factory->createListFromLoader(ChoiceList::loader($type, $loader), ChoiceList::value($type, $closure));
        $list2 = $this->factory->createListFromLoader(ChoiceList::loader($type, new ArrayChoiceLoader()), ChoiceList::value($type, static function (): void {}));

        $this->assertSame($list1, $list2);
        self::assertEqualsLazyChoiceList(new LazyChoiceList($loader, $closure), $list1);
        self::assertEqualsLazyChoiceList(new LazyChoiceList(new ArrayChoiceLoader(), static function (): void {}), $list2);
    }

    public function testCreateFromLoaderDifferentValueClosure(): void
    {
        $loader = new ArrayChoiceLoader();
        $closure1 = static function (): void {};
        $closure2 = static function (): void {};

        $this->assertNotSame($this->factory->createListFromLoader($loader, $closure1), $this->factory->createListFromLoader($loader, $closure2));
    }

    public function testCreateFromLoaderSameFilterClosure(): void
    {
        $loader = new ArrayChoiceLoader();
        $type = new FormType();
        $closure = static function (): void {};

        $list1 = $this->factory->createListFromLoader(ChoiceList::loader($type, $loader), null, $closure);
        $list2 = $this->factory->createListFromLoader(ChoiceList::loader($type, new ArrayChoiceLoader()), null, $closure);

        $this->assertNotSame($list1, $list2);
        self::assertEqualsLazyChoiceList(new LazyChoiceList(new FilterChoiceLoaderDecorator($loader, $closure)), $list1);
        self::assertEqualsLazyChoiceList(new LazyChoiceList(new FilterChoiceLoaderDecorator(new ArrayChoiceLoader(), $closure)), $list2);
    }

    public function testCreateFromLoaderSameFilterClosureUseCache(): void
    {
        $type = new FormType();
        $choiceFilter = ChoiceList::filter($type, static function (): void {});
        $list1 = $this->factory->createListFromLoader(ChoiceList::loader($type, new ArrayChoiceLoader()), null, $choiceFilter);
        $list2 = $this->factory->createListFromLoader(ChoiceList::loader($type, new ArrayChoiceLoader()), null, $choiceFilter);

        $this->assertSame($list1, $list2);
        self::assertEqualsLazyChoiceList(new LazyChoiceList(new FilterChoiceLoaderDecorator(new ArrayChoiceLoader(), static function (): void {})), $list1);
        self::assertEqualsLazyChoiceList(new LazyChoiceList(new FilterChoiceLoaderDecorator(new ArrayChoiceLoader(), static function (): void {})), $list2);
    }

    public function testCreateFromLoaderDifferentFilterClosure(): void
    {
        $type = new FormType();
        $closure1 = static function (): void {};
        $closure2 = static function (): void {};
        $list1 = $this->factory->createListFromLoader(ChoiceList::loader($type, new ArrayChoiceLoader()), null, $closure1);
        $list2 = $this->factory->createListFromLoader(ChoiceList::loader($type, new ArrayChoiceLoader()), null, $closure2);

        $this->assertNotSame($list1, $list2);
        self::assertEqualsLazyChoiceList(new LazyChoiceList(new FilterChoiceLoaderDecorator(new ArrayChoiceLoader(), $closure1), null), $list1);
        self::assertEqualsLazyChoiceList(new LazyChoiceList(new FilterChoiceLoaderDecorator(new ArrayChoiceLoader(), $closure2), null), $list2);
    }

    public function testCreateViewSamePreferredChoices(): void
    {
        $preferred = ['a'];
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred);
        $view2 = $this->factory->createView($list, $preferred);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSamePreferredChoicesUseCache(): void
    {
        $preferred = ['a'];
        $type = new FormType();
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, ChoiceList::preferred($type, $preferred));
        $view2 = $this->factory->createView($list, ChoiceList::preferred($type, ['a']));

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewDifferentPreferredChoices(): void
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

    public function testCreateViewSamePreferredChoicesClosure(): void
    {
        $preferred = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred);
        $view2 = $this->factory->createView($list, $preferred);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSamePreferredChoicesClosureUseCache(): void
    {
        $preferredCallback = static function (): void {};
        $type = new FormType();
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, ChoiceList::preferred($type, $preferredCallback));
        $view2 = $this->factory->createView($list, ChoiceList::preferred($type, static function (): void {}));

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewDifferentPreferredChoicesClosure(): void
    {
        $preferred1 = static function (): void {};
        $preferred2 = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred1);
        $view2 = $this->factory->createView($list, $preferred2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameLabelClosure(): void
    {
        $labels = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, $labels);
        $view2 = $this->factory->createView($list, null, $labels);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameLabelClosureUseCache(): void
    {
        $labelsCallback = static function (): void {};
        $type = new FormType();
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, ChoiceList::label($type, $labelsCallback));
        $view2 = $this->factory->createView($list, null, ChoiceList::label($type, static function (): void {}));

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewDifferentLabelClosure(): void
    {
        $labels1 = static function (): void {};
        $labels2 = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, $labels1);
        $view2 = $this->factory->createView($list, null, $labels2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameIndexClosure(): void
    {
        $index = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, $index);
        $view2 = $this->factory->createView($list, null, null, $index);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameIndexClosureUseCache(): void
    {
        $indexCallback = static function (): void {};
        $type = new FormType();
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, ChoiceList::fieldName($type, $indexCallback));
        $view2 = $this->factory->createView($list, null, null, ChoiceList::fieldName($type, static function (): void {}));

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewDifferentIndexClosure(): void
    {
        $index1 = static function (): void {};
        $index2 = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, $index1);
        $view2 = $this->factory->createView($list, null, null, $index2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameGroupByClosure(): void
    {
        $groupBy = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, $groupBy);
        $view2 = $this->factory->createView($list, null, null, null, $groupBy);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameGroupByClosureUseCache(): void
    {
        $groupByCallback = static function (): void {};
        $type = new FormType();
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, ChoiceList::groupBy($type, $groupByCallback));
        $view2 = $this->factory->createView($list, null, null, null, ChoiceList::groupBy($type, static function (): void {}));

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewDifferentGroupByClosure(): void
    {
        $groupBy1 = static function (): void {};
        $groupBy2 = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, $groupBy1);
        $view2 = $this->factory->createView($list, null, null, null, $groupBy2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameAttributes(): void
    {
        $attr = ['class' => 'foobar'];
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, null, $attr);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameAttributesUseCache(): void
    {
        $attr = ['class' => 'foobar'];
        $type = new FormType();
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, null, ChoiceList::attr($type, $attr));
        $view2 = $this->factory->createView($list, null, null, null, null, ChoiceList::attr($type, ['class' => 'foobar']));

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewDifferentAttributes(): void
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

    public function testCreateViewSameAttributesClosure(): void
    {
        $attr = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, null, $attr);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewSameAttributesClosureUseCache(): void
    {
        $attrCallback = static function (): void {};
        $type = new FormType();
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, null, ChoiceList::attr($type, $attrCallback));
        $view2 = $this->factory->createView($list, null, null, null, null, ChoiceList::attr($type, static function (): void {}));

        $this->assertSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public function testCreateViewDifferentAttributesClosure(): void
    {
        $attr1 = static function (): void {};
        $attr2 = static function (): void {};
        $list = new ArrayChoiceList([]);

        $view1 = $this->factory->createView($list, null, null, null, null, $attr1);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr2);

        $this->assertNotSame($view1, $view2);
        $this->assertEquals(new ChoiceListView(), $view1);
        $this->assertEquals(new ChoiceListView(), $view2);
    }

    public static function provideSameChoices()
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

    public static function provideDistinguishedChoices()
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

    public static function provideSameKeyChoices()
    {
        // Only test types here that can be used as array keys
        return [
            [0, 0],
            [0, '0'],
            ['a', 'a'],
            [\chr(181).'meter', \chr(181).'meter'],
        ];
    }

    public static function provideDistinguishedKeyChoices()
    {
        // Only test types here that can be used as array keys
        return [
            [0, ''],
            [1, 'a'],
            ['', 'a'],
        ];
    }
}
