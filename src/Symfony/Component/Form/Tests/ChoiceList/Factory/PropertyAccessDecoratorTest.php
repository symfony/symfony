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

use Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyAccessDecoratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $decoratedFactory;

    /**
     * @var PropertyAccessDecorator
     */
    private $factory;

    protected function setUp()
    {
        $this->decoratedFactory = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface')->getMock();
        $this->factory = new PropertyAccessDecorator($this->decoratedFactory);
    }

    public function testCreateFromChoicesPropertyPath()
    {
        $choices = array((object) array('property' => 'value'));

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($choices, $callback) {
                return array_map($callback, $choices);
            }));

        $this->assertSame(array('value'), $this->factory->createListFromChoices($choices, 'property'));
    }

    public function testCreateFromChoicesPropertyPathInstance()
    {
        $choices = array((object) array('property' => 'value'));

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($choices, $callback) {
                return array_map($callback, $choices);
            }));

        $this->assertSame(array('value'), $this->factory->createListFromChoices($choices, new PropertyPath('property')));
    }

    /**
     * @group legacy
     */
    public function testCreateFromChoicesPropertyPathWithCallableString()
    {
        $choices = array('foo' => 'bar');

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createListFromChoices($choices, 'end'));
    }

    public function testCreateFromLoaderPropertyPath()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($loader, $callback) {
                return $callback((object) array('property' => 'value'));
            }));

        $this->assertSame('value', $this->factory->createListFromLoader($loader, 'property'));
    }

    /**
     * @group legacy
     */
    public function testCreateFromLoaderPropertyPathWithCallableString()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createListFromLoader($loader, 'end'));
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateFromChoicesAssumeNullIfValuePropertyPathUnreadable()
    {
        $choices = array(null);

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($choices, $callback) {
                return array_map($callback, $choices);
            }));

        $this->assertSame(array(null), $this->factory->createListFromChoices($choices, 'property'));
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateFromChoiceLoaderAssumeNullIfValuePropertyPathUnreadable()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($loader, $callback) {
                return $callback(null);
            }));

        $this->assertNull($this->factory->createListFromLoader($loader, 'property'));
    }

    public function testCreateFromLoaderPropertyPathInstance()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($loader, $callback) {
                return $callback((object) array('property' => 'value'));
            }));

        $this->assertSame('value', $this->factory->createListFromLoader($loader, new PropertyPath('property')));
    }

    public function testCreateViewPreferredChoicesAsPropertyPath()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred) {
                return $preferred((object) array('property' => true));
            }));

        $this->assertTrue($this->factory->createView(
            $list,
            'property'
        ));
    }

    /**
     * @group legacy
     */
    public function testCreateViewPreferredChoicesAsPropertyPathWithCallableString()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createView(
            $list,
            'end'
        ));
    }

    public function testCreateViewPreferredChoicesAsPropertyPathInstance()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred) {
                return $preferred((object) array('property' => true));
            }));

        $this->assertTrue($this->factory->createView(
            $list,
            new PropertyPath('property')
        ));
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateViewAssumeNullIfPreferredChoicesPropertyPathUnreadable()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred) {
                return $preferred((object) array('category' => null));
            }));

        $this->assertFalse($this->factory->createView(
            $list,
            'category.preferred'
        ));
    }

    public function testCreateViewLabelsAsPropertyPath()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred, $label) {
                return $label((object) array('property' => 'label'));
            }));

        $this->assertSame('label', $this->factory->createView(
            $list,
            null, // preferred choices
            'property'
        ));
    }

    /**
     * @group legacy
     */
    public function testCreateViewLabelsAsPropertyPathWithCallableString()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createView(
            $list,
            null, // preferred choices
            'end'
        ));
    }

    public function testCreateViewLabelsAsPropertyPathInstance()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred, $label) {
                return $label((object) array('property' => 'label'));
            }));

        $this->assertSame('label', $this->factory->createView(
            $list,
            null, // preferred choices
            new PropertyPath('property')
        ));
    }

    public function testCreateViewIndicesAsPropertyPath()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred, $label, $index) {
                return $index((object) array('property' => 'index'));
            }));

        $this->assertSame('index', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            'property'
        ));
    }

    /**
     * @group legacy
     */
    public function testCreateViewIndicesAsPropertyPathWithCallableString()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            'end'
        ));
    }

    public function testCreateViewIndicesAsPropertyPathInstance()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred, $label, $index) {
                return $index((object) array('property' => 'index'));
            }));

        $this->assertSame('index', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            new PropertyPath('property')
        ));
    }

    public function testCreateViewGroupsAsPropertyPath()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred, $label, $index, $groupBy) {
                return $groupBy((object) array('property' => 'group'));
            }));

        $this->assertSame('group', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            null, // index
            'property'
        ));
    }

    /**
     * @group legacy
     */
    public function testCreateViewGroupsAsPropertyPathWithCallableString()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            null, // index
            'end'
        ));
    }

    public function testCreateViewGroupsAsPropertyPathInstance()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred, $label, $index, $groupBy) {
                return $groupBy((object) array('property' => 'group'));
            }));

        $this->assertSame('group', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            null, // index
            new PropertyPath('property')
        ));
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateViewAssumeNullIfGroupsPropertyPathUnreadable()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred, $label, $index, $groupBy) {
                return $groupBy((object) array('group' => null));
            }));

        $this->assertNull($this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            null, // index
            'group.name'
        ));
    }

    public function testCreateViewAttrAsPropertyPath()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred, $label, $index, $groupBy, $attr) {
                return $attr((object) array('property' => 'attr'));
            }));

        $this->assertSame('attr', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            null, // index
            null, // groups
            'property'
        ));
    }

    /**
     * @group legacy
     */
    public function testCreateViewAttrAsPropertyPathWithCallableString()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            null, // inde
            null, // groups
            'end'
        ));
    }

    public function testCreateViewAttrAsPropertyPathInstance()
    {
        $list = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, $this->isInstanceOf('\Closure'))
            ->will($this->returnCallback(function ($list, $preferred, $label, $index, $groupBy, $attr) {
                return $attr((object) array('property' => 'attr'));
            }));

        $this->assertSame('attr', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            null, // index
            null, // groups
            new PropertyPath('property')
        ));
    }
}
