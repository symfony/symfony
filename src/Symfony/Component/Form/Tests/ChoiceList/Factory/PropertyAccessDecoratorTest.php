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
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Tests\Fixtures\ArrayChoiceLoader;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyAccessDecoratorTest extends TestCase
{
    /**
     * @var MockObject&ChoiceListFactoryInterface
     */
    private $decoratedFactory;

    /**
     * @var PropertyAccessDecorator
     */
    private $factory;

    protected function setUp(): void
    {
        $this->decoratedFactory = $this->createMock(ChoiceListFactoryInterface::class);
        $this->factory = new PropertyAccessDecorator(new DefaultChoiceListFactory());
    }

    public function testCreateFromChoicesPropertyPath()
    {
        $object = (object) ['property' => 'value'];

        $this->assertSame(['value' => $object], $this->factory->createListFromChoices([$object], 'property')->getChoices());
    }

    public function testCreateFromChoicesPropertyPathInstance()
    {
        $object = (object) ['property' => 'value'];

        $this->assertSame(['value' => $object], $this->factory->createListFromChoices([$object], new PropertyPath('property'))->getChoices());
    }

    public function testCreateFromChoicesFilterPropertyPath()
    {
        $factory = new PropertyAccessDecorator($this->decoratedFactory);
        $filteredChoices = [
            'two' => (object) ['property' => 'value 2', 'filter' => true],
        ];
        $choices = [
            'one' => (object) ['property' => 'value 1', 'filter' => false],
        ] + $filteredChoices;

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $this->isInstanceOf(\Closure::class), $this->isInstanceOf(\Closure::class))
            ->willReturnCallback(function ($choices, $value, $callback) {
                return new ArrayChoiceList(array_map($value, array_filter($choices, $callback)));
            });

        $this->assertSame(['value 2' => 'value 2'], $factory->createListFromChoices($choices, 'property', 'filter')->getChoices());
    }

    public function testCreateFromChoicesFilterPropertyPathInstance()
    {
        $factory = new PropertyAccessDecorator($this->decoratedFactory);
        $filteredChoices = [
            'two' => (object) ['property' => 'value 2', 'filter' => true],
        ];
        $choices = [
                'one' => (object) ['property' => 'value 1', 'filter' => false],
        ] + $filteredChoices;

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $this->isInstanceOf(\Closure::class), $this->isInstanceOf(\Closure::class))
            ->willReturnCallback(function ($choices, $value, $callback) {
                return new ArrayChoiceList(array_map($value, array_filter($choices, $callback)));
            });

        $this->assertSame(
            ['value 2' => 'value 2'],
            $factory->createListFromChoices($choices, new PropertyPath('property'), new PropertyPath('filter'))->getChoices()
        );
    }

    public function testCreateFromLoaderPropertyPath()
    {
        $object = (object) ['property' => 'value'];
        $loader = new ArrayChoiceLoader([$object]);

        $this->assertSame(['value' => $object], $this->factory->createListFromLoader($loader, 'property')->getChoices());
    }

    public function testCreateFromLoaderFilterPropertyPath()
    {
        $factory = new PropertyAccessDecorator($this->decoratedFactory);
        $loader = $this->createMock(ChoiceLoaderInterface::class);
        $filteredChoices = [
            'two' => (object) ['property' => 'value 2', 'filter' => true],
        ];
        $choices = [
                'one' => (object) ['property' => 'value 1', 'filter' => false],
        ] + $filteredChoices;

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, $this->isInstanceOf(\Closure::class), $this->isInstanceOf(\Closure::class))
            ->willReturnCallback(function ($loader, $value, $callback) use ($choices) {
                return new ArrayChoiceList(array_map($value, array_filter($choices, $callback)));
            });

        $this->assertSame(['value 2' => 'value 2'], $factory->createListFromLoader($loader, 'property', 'filter')->getChoices());
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateFromChoicesAssumeNullIfValuePropertyPathUnreadable()
    {
        $choices = [null];

        $this->assertSame(['' => null], $this->factory->createListFromChoices($choices, 'property')->getChoices());
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateFromChoiceLoaderAssumeNullIfValuePropertyPathUnreadable()
    {
        $loader = new ArrayChoiceLoader([null]);

        $this->assertSame(['' => null], $this->factory->createListFromLoader($loader, 'property')->getChoices());
    }

    public function testCreateFromLoaderPropertyPathInstance()
    {
        $object = (object) ['property' => 'value'];
        $loader = new ArrayChoiceLoader([$object]);

        $this->assertSame(['value' => $object], $this->factory->createListFromLoader($loader, new PropertyPath('property'))->getChoices());
    }

    public function testCreateViewPreferredChoicesAsPropertyPath()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals([new ChoiceView($object, '0', '0')], $this->factory->createView($list, 'preferred_choice')->choices);
        $this->assertEquals([new ChoiceView($object, '0', '0')], $this->factory->createView($list, 'preferred_choice')->preferredChoices);
    }

    public function testCreateViewPreferredChoicesAsPropertyPathInstance()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals([new ChoiceView($object, '0', '0')], $this->factory->createView($list, new PropertyPath('preferred_choice'))->choices);
        $this->assertEquals([new ChoiceView($object, '0', '0')], $this->factory->createView($list, new PropertyPath('preferred_choice'))->preferredChoices);
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateViewAssumeNullIfPreferredChoicesPropertyPathUnreadable()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals([new ChoiceView($object, '0', '0')], $this->factory->createView($list, new PropertyPath('preferred_choice.property'))->choices);
        $this->assertEquals([], $this->factory->createView($list, new PropertyPath('preferred_choice.property'))->preferredChoices);
    }

    public function testCreateViewLabelsAsPropertyPath()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals([new ChoiceView($object, '0', 'foo')], $this->factory->createView($list, null, 'view_label')->choices);
    }

    public function testCreateViewLabelsAsPropertyPathInstance()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals([new ChoiceView($object, '0', 'foo')], $this->factory->createView($list, null, new PropertyPath('view_label'))->choices);
    }

    public function testCreateViewIndicesAsPropertyPath()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals(['key' => new ChoiceView($object, '0', '0')], $this->factory->createView($list, null, null, 'view_index')->choices);
    }

    public function testCreateViewIndicesAsPropertyPathInstance()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals(['key' => new ChoiceView($object, '0', '0')], $this->factory->createView($list, null, null, new PropertyPath('view_index'))->choices);
    }

    public function testCreateViewGroupsAsPropertyPath()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals(['bar' => new ChoiceGroupView('bar', [new ChoiceView($object, '0', '0')])], $this->factory->createView($list, null, null, null, 'view_group')->choices);
    }

    public function testCreateViewGroupsAsPropertyPathInstance()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals(['bar' => new ChoiceGroupView('bar', [new ChoiceView($object, '0', '0')])], $this->factory->createView($list, null, null, null, new PropertyPath('view_group'))->choices);
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateViewAssumeNullIfGroupsPropertyPathUnreadable()
    {
        $list = new ArrayChoiceList([]);

        $this->assertSame([], $this->factory->createView($list, null, null, null, 'group.name')->choices);
    }

    public function testCreateViewAttrAsPropertyPath()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals([new ChoiceView($object, '0', '0', ['baz' => 'foobar'])], $this->factory->createView($list, null, null, null, null, 'view_attribute')->choices);
    }

    public function testCreateViewAttrAsPropertyPathInstance()
    {
        $object = (object) ['preferred_choice' => true, 'view_label' => 'foo', 'view_index' => 'key', 'view_group' => 'bar', 'view_attribute' => ['baz' => 'foobar']];
        $list = new ArrayChoiceList([$object]);

        $this->assertEquals([new ChoiceView($object, '0', '0', ['baz' => 'foobar'])], $this->factory->createView($list, null, null, null, null, new PropertyPath('view_attribute'))->choices);
    }
}
