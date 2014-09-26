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

use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

class DefaultChoiceListFactoryTest extends \PHPUnit_Framework_TestCase
{
    private $obj1;

    private $obj2;

    private $obj3;

    private $obj4;

    private $list;

    /**
     * @var DefaultChoiceListFactory
     */
    private $factory;

    public function getValue($object)
    {
        return $object->value;
    }

    public function getScalarValue($choice)
    {
        switch ($choice) {
            case 'a': return 'a';
            case 'b': return 'b';
            case 'c': return '1';
            case 'd': return '2';
        }
    }

    public function getLabel($object)
    {
        return $object->label;
    }

    public function getFormIndex($object)
    {
        return $object->index;
    }

    public function isPreferred($object)
    {
        return $this->obj2 === $object || $this->obj3 === $object;
    }

    public function getAttr($object)
    {
        return $object->attr;
    }

    public function getGroup($object)
    {
        return $this->obj1 === $object || $this->obj2 === $object ? 'Group 1' : 'Group 2';
    }

    protected function setUp()
    {
        $this->obj1 = (object) array('label' => 'A', 'index' => 'w', 'value' => 'a', 'preferred' => false, 'group' => 'Group 1', 'attr' => array());
        $this->obj2 = (object) array('label' => 'B', 'index' => 'x', 'value' => 'b', 'preferred' => true, 'group' => 'Group 1', 'attr' => array('attr1' => 'value1'));
        $this->obj3 = (object) array('label' => 'C', 'index' => 'y', 'value' => 1, 'preferred' => true, 'group' => 'Group 2', 'attr' => array('attr2' => 'value2'));
        $this->obj4 = (object) array('label' => 'D', 'index' => 'z', 'value' => 2, 'preferred' => false, 'group' => 'Group 2', 'attr' => array());
        $this->list = new ChoiceList(
            array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4),
            array('A' => '0', 'B' => '1', 'C' => '2', 'D' => '3')
        );
        $this->factory = new DefaultChoiceListFactory();
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateFromChoicesFailsIfChoicesNotArrayOrTraversable()
    {
        $this->factory->createListFromChoices('foobar');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateFromChoicesFailsIfValuesNotCallableOrString()
    {
        $this->factory->createListFromChoices(array(), new \stdClass());
    }

    public function testCreateFromChoicesEmpty()
    {
        $list = $this->factory->createListFromChoices(array());

        $this->assertSame(array(), $list->getChoices());
        $this->assertSame(array(), $list->getValues());
    }

    public function testCreateFromChoicesFlat()
    {
        $list = $this->factory->createListFromChoices(
            array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4)
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    public function testCreateFromChoicesFlatTraversable()
    {
        $list = $this->factory->createListFromChoices(
            new \ArrayIterator(array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4))
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    public function testCreateFromChoicesFlatValuesAsCallable()
    {
        $list = $this->factory->createListFromChoices(
            array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4),
            array($this, 'getValue')
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromChoicesFlatValuesAsClosure()
    {
        $list = $this->factory->createListFromChoices(
            array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4),
            function ($object) { return $object->value; }
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromChoicesFlatValuesClosureReceivesKey()
    {
        $list = $this->factory->createListFromChoices(
            array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4),
            function ($object, $key) {
                switch ($key) {
                    case 'A': return 'a';
                    case 'B': return 'b';
                    case 'C': return '1';
                    case 'D': return '2';
                }
            }
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromChoicesGrouped()
    {
        $list = $this->factory->createListFromChoices(
            array(
                'Group 1' => array('A' => $this->obj1, 'B' => $this->obj2),
                'Group 2' => array('C' => $this->obj3, 'D' => $this->obj4),
            )
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    public function testCreateFromChoicesGroupedTraversable()
    {
        $list = $this->factory->createListFromChoices(
            new \ArrayIterator(array(
                    'Group 1' => array('A' => $this->obj1, 'B' => $this->obj2),
                    'Group 2' => array('C' => $this->obj3, 'D' => $this->obj4),
                ))
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    public function testCreateFromChoicesGroupedValuesAsCallable()
    {
        $list = $this->factory->createListFromChoices(
            array(
                'Group 1' => array('A' => $this->obj1, 'B' => $this->obj2),
                'Group 2' => array('C' => $this->obj3, 'D' => $this->obj4),
            ),
            array($this, 'getValue')
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromChoicesGroupedValuesAsClosure()
    {
        $list = $this->factory->createListFromChoices(
            array(
                'Group 1' => array('A' => $this->obj1, 'B' => $this->obj2),
                'Group 2' => array('C' => $this->obj3, 'D' => $this->obj4),
            ),
            function ($object) { return $object->value; }
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromChoicesGroupedValuesAsClosureReceivesKey()
    {
        $list = $this->factory->createListFromChoices(
            array(
                'Group 1' => array('A' => $this->obj1, 'B' => $this->obj2),
                'Group 2' => array('C' => $this->obj3, 'D' => $this->obj4),
            ),
            function ($object, $key) {
                switch ($key) {
                    case 'A': return 'a';
                    case 'B': return 'b';
                    case 'C': return '1';
                    case 'D': return '2';
                }
            }
        );

        $this->assertObjectListWithCustomValues($list);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateFromFlippedChoicesFailsIfChoicesNotArrayOrTraversable()
    {
        $this->factory->createListFromFlippedChoices('foobar');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateFromFlippedChoicesFailsIfValuesNotCallableOrString()
    {
        $this->factory->createListFromFlippedChoices(array(), new \stdClass());
    }

    public function testCreateFromFlippedChoicesEmpty()
    {
        $list = $this->factory->createListFromFlippedChoices(array());

        $this->assertSame(array(), $list->getChoices());
        $this->assertSame(array(), $list->getValues());
    }

    public function testCreateFromFlippedChoicesFlat()
    {
        $list = $this->factory->createListFromFlippedChoices(
            array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D')
        );

        $this->assertScalarListWithGeneratedValues($list);
    }

    public function testCreateFromFlippedChoicesFlatTraversable()
    {
        $list = $this->factory->createListFromFlippedChoices(
            new \ArrayIterator(array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'))
        );

        $this->assertScalarListWithGeneratedValues($list);
    }

    public function testCreateFromFlippedChoicesFlatValuesAsCallable()
    {
        $list = $this->factory->createListFromFlippedChoices(
            array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'),
            array($this, 'getScalarValue')
        );

        $this->assertScalarListWithCustomValues($list);
    }

    public function testCreateFromFlippedChoicesFlatValuesAsClosure()
    {
        $list = $this->factory->createListFromFlippedChoices(
            array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'),
            function ($choice) {
                switch ($choice) {
                    case 'a': return 'a';
                    case 'b': return 'b';
                    case 'c': return '1';
                    case 'd': return '2';
                }
            }
        );

        $this->assertScalarListWithCustomValues($list);
    }

    public function testCreateFromFlippedChoicesFlatValuesClosureReceivesKey()
    {
        $list = $this->factory->createListFromFlippedChoices(
            array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'),
            function ($choice, $key) {
                switch ($key) {
                    case 'A': return 'a';
                    case 'B': return 'b';
                    case 'C': return '1';
                    case 'D': return '2';
                }
            }
        );

        $this->assertScalarListWithCustomValues($list);
    }

    public function testCreateFromFlippedChoicesGrouped()
    {
        $list = $this->factory->createListFromFlippedChoices(
            array(
                'Group 1' => array('a' => 'A', 'b' => 'B'),
                'Group 2' => array('c' => 'C', 'd' => 'D'),
            )
        );

        $this->assertScalarListWithGeneratedValues($list);
    }

    public function testCreateFromFlippedChoicesGroupedTraversable()
    {
        $list = $this->factory->createListFromFlippedChoices(
            new \ArrayIterator(array(
                    'Group 1' => array('a' => 'A', 'b' => 'B'),
                    'Group 2' => array('c' => 'C', 'd' => 'D'),
                ))
        );

        $this->assertScalarListWithGeneratedValues($list);
    }

    public function testCreateFromFlippedChoicesGroupedValuesAsCallable()
    {
        $list = $this->factory->createListFromFlippedChoices(
            array(
                'Group 1' => array('a' => 'A', 'b' => 'B'),
                'Group 2' => array('c' => 'C', 'd' => 'D'),
            ),
            array($this, 'getScalarValue')
        );

        $this->assertScalarListWithCustomValues($list);
    }

    public function testCreateFromFlippedChoicesGroupedValuesAsClosure()
    {
        $list = $this->factory->createListFromFlippedChoices(
            array(
                'Group 1' => array('a' => 'A', 'b' => 'B'),
                'Group 2' => array('c' => 'C', 'd' => 'D'),
            ),
            function ($choice) {
                switch ($choice) {
                    case 'a': return 'a';
                    case 'b': return 'b';
                    case 'c': return '1';
                    case 'd': return '2';
                }
            }
        );

        $this->assertScalarListWithCustomValues($list);
    }

    public function testCreateFromFlippedChoicesGroupedValuesAsClosureReceivesKey()
    {
        $list = $this->factory->createListFromFlippedChoices(
            array(
                'Group 1' => array('a' => 'A', 'b' => 'B'),
                'Group 2' => array('c' => 'C', 'd' => 'D'),
            ),
            function ($choice, $key) {
                switch ($key) {
                    case 'A': return 'a';
                    case 'B': return 'b';
                    case 'C': return '1';
                    case 'D': return '2';
                }
            }
        );

        $this->assertScalarListWithCustomValues($list);
    }

    public function testCreateFromLoader()
    {
        $loader = $this->getMock('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface');

        $list = $this->factory->createListFromLoader($loader);

        $this->assertEquals(new LazyChoiceList($loader), $list);
    }

    public function testCreateFromLoaderWithValues()
    {
        $loader = $this->getMock('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface');

        $value = function () {};
        $list = $this->factory->createListFromLoader($loader, $value);

        $this->assertEquals(new LazyChoiceList($loader, $value), $list);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateFromLoaderFailsIfValuesNotCallableOrString()
    {
        $loader = $this->getMock('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface');

        $this->factory->createListFromLoader($loader, new \stdClass());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateViewFailsIfPreferredChoicesInvalid()
    {
        $this->factory->createView($this->list, new \stdClass());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateViewFailsIfLabelInvalid()
    {
        $this->factory->createView($this->list, null, new \stdClass());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateViewFailsIfIndexInvalid()
    {
        $this->factory->createView($this->list, null, null, new \stdClass());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateViewFailsIfGroupByInvalid()
    {
        $this->factory->createView($this->list, null, null, null, new \stdClass());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testCreateViewFailsIfAttrInvalid()
    {
        $this->factory->createView($this->list, null, null, null, null, new \stdClass());
    }

    public function testCreateViewFlat()
    {
        $view = $this->factory->createView($this->list);

        $this->assertEquals(new ChoiceListView(
                array(
                    0 => new ChoiceView('A', '0', $this->obj1),
                    1 => new ChoiceView('B', '1', $this->obj2),
                    2 => new ChoiceView('C', '2', $this->obj3),
                    3 => new ChoiceView('D', '3', $this->obj4),
                ), array()
        ), $view);
    }

    public function testCreateViewFlatPreferredChoices()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3)
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatPreferredChoicesEmptyArray()
    {
        $view = $this->factory->createView(
            $this->list,
            array()
        );

        $this->assertEquals(new ChoiceListView(
                array(
                    0 => new ChoiceView('A', '0', $this->obj1),
                    1 => new ChoiceView('B', '1', $this->obj2),
                    2 => new ChoiceView('C', '2', $this->obj3),
                    3 => new ChoiceView('D', '3', $this->obj4),
                ), array()
        ), $view);
    }

    public function testCreateViewFlatPreferredChoicesAsCallable()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this, 'isPreferred')
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatPreferredChoicesAsClosure()
    {
        $obj2 = $this->obj2;
        $obj3 = $this->obj3;

        $view = $this->factory->createView(
            $this->list,
            function ($object) use ($obj2, $obj3) {
                return $obj2 === $object || $obj3 === $object;
            }
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatPreferredChoicesClosureReceivesKey()
    {
        $obj2 = $this->obj2;
        $obj3 = $this->obj3;

        $view = $this->factory->createView(
            $this->list,
            function ($object, $key) use ($obj2, $obj3) {
                return 'B' === $key || 'C' === $key;
            }
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatLabelAsCallable()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            array($this, 'getLabel')
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatLabelAsClosure()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            function ($object) {
                return $object->label;
            }
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatLabelClosureReceivesKey()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            function ($object, $key) {
                return $key;
            }
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatIndexAsCallable()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            array($this, 'getFormIndex')
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    public function testCreateViewFlatIndexAsClosure()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            function ($object) {
                return $object->index;
            }
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    public function testCreateViewFlatIndexClosureReceivesKey()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            function ($object, $key) {
                switch ($key) {
                    case 'A': return 'w';
                    case 'B': return 'x';
                    case 'C': return 'y';
                    case 'D': return 'z';
                }
            }
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    public function testCreateViewFlatGroupByAsArray()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            array(
                'Group 1' => array('A' => true, 'B' => true),
                'Group 2' => array('C' => true, 'D' => true),
            )
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatGroupByAsTraversable()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            new \ArrayIterator(array(
                'Group 1' => array('A' => true, 'B' => true),
                'Group 2' => array('C' => true, 'D' => true),
            ))
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatGroupByEmpty()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            array() // ignored
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatGroupByAsCallable()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            array($this, 'getGroup')
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatGroupByAsClosure()
    {
        $obj1 = $this->obj1;
        $obj2 = $this->obj2;

        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            function ($object) use ($obj1, $obj2) {
                return $obj1 === $object || $obj2 === $object ? 'Group 1'
                    : 'Group 2';
            }
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatGroupByClosureReceivesKey()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            function ($object, $key) {
                return 'A' === $key || 'B' === $key ? 'Group 1' : 'Group 2';
            }
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatAttrAsArray()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            array(
                'B' => array('attr1' => 'value1'),
                'C' => array('attr2' => 'value2')
            )
        );

        $this->assertFlatViewWithAttr($view);
    }

    public function testCreateViewFlatAttrEmpty()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            array()
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatAttrAsCallable()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            array($this, 'getAttr')
        );

        $this->assertFlatViewWithAttr($view);
    }

    public function testCreateViewFlatAttrAsClosure()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            function ($object) {
                return $object->attr;
            }
        );

        $this->assertFlatViewWithAttr($view);
    }

    public function testCreateViewFlatAttrClosureReceivesKey()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            function ($object, $key) {
                switch ($key) {
                    case 'B': return array('attr1' => 'value1');
                    case 'C': return array('attr2' => 'value2');
                    default: return array();
                }
            }
        );

        $this->assertFlatViewWithAttr($view);
    }

    public function testCreateViewForLegacyChoiceList()
    {
        $preferred = array(new ChoiceView('Preferred', 'x', 'x'));
        $other = array(new ChoiceView('Other', 'y', 'y'));

        $list = $this->getMock('Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface');

        $list->expects($this->once())
            ->method('getPreferredViews')
            ->will($this->returnValue($preferred));
        $list->expects($this->once())
            ->method('getRemainingViews')
            ->will($this->returnValue($other));

        $view = $this->factory->createView($list);

        $this->assertSame($other, $view->choices);
        $this->assertSame($preferred, $view->preferredChoices);
    }

    private function assertScalarListWithGeneratedValues(ChoiceListInterface $list)
    {
        $this->assertSame(array(
            'A' => 'a',
            'B' => 'b',
            'C' => 'c',
            'D' => 'd',
        ), $list->getChoices());

        $this->assertSame(array(
            'A' => 'a',
            'B' => 'b',
            'C' => 'c',
            'D' => 'd',
        ), $list->getValues());
    }

    private function assertObjectListWithGeneratedValues(ChoiceListInterface $list)
    {
        $this->assertSame(array(
            'A' => $this->obj1,
            'B' => $this->obj2,
            'C' => $this->obj3,
            'D' => $this->obj4,
        ), $list->getChoices());

        $this->assertSame(array(
            'A' => '0',
            'B' => '1',
            'C' => '2',
            'D' => '3',
        ), $list->getValues());
    }

    private function assertScalarListWithCustomValues(ChoiceListInterface $list)
    {
        $this->assertSame(array(
            'A' => 'a',
            'B' => 'b',
            'C' => 'c',
            'D' => 'd',
        ), $list->getChoices());

        $this->assertSame(array(
            'A' => 'a',
            'B' => 'b',
            'C' => '1',
            'D' => '2',
        ), $list->getValues());
    }

    private function assertObjectListWithCustomValues(ChoiceListInterface $list)
    {
        $this->assertSame(array(
            'A' => $this->obj1,
            'B' => $this->obj2,
            'C' => $this->obj3,
            'D' => $this->obj4,
        ), $list->getChoices());

        $this->assertSame(array(
            'A' => 'a',
            'B' => 'b',
            'C' => '1',
            'D' => '2',
        ), $list->getValues());
    }

    private function assertFlatView($view)
    {
        $this->assertEquals(new ChoiceListView(
                array(
                    0 => new ChoiceView('A', '0', $this->obj1),
                    3 => new ChoiceView('D', '3', $this->obj4),
                ), array(
                    1 => new ChoiceView('B', '1', $this->obj2),
                    2 => new ChoiceView('C', '2', $this->obj3),
                )
        ), $view);
    }

    private function assertFlatViewWithCustomIndices($view)
    {
        $this->assertEquals(new ChoiceListView(
                array(
                    'w' => new ChoiceView('A', '0', $this->obj1),
                    'z' => new ChoiceView('D', '3', $this->obj4),
                ), array(
                    'x' => new ChoiceView('B', '1', $this->obj2),
                    'y' => new ChoiceView('C', '2', $this->obj3),
                )
        ), $view);
    }

    private function assertFlatViewWithAttr($view)
    {
        $this->assertEquals(new ChoiceListView(
                array(
                    0 => new ChoiceView('A', '0', $this->obj1),
                    3 => new ChoiceView('D', '3', $this->obj4),
                ), array(
                    1 => new ChoiceView(
                        'B',
                        '1',
                        $this->obj2,
                        array('attr1' => 'value1')
                    ),
                    2 => new ChoiceView(
                        'C',
                        '2',
                        $this->obj3,
                        array('attr2' => 'value2')
                    ),
                )
        ), $view);
    }

    private function assertGroupedView($view)
    {
        $this->assertEquals(new ChoiceListView(
                array(
                    'Group 1' => new ChoiceGroupView(
                        'Group 1',
                        array(0 => new ChoiceView('A', '0', $this->obj1))
                    ),
                    'Group 2' => new ChoiceGroupView(
                        'Group 2',
                        array(3 => new ChoiceView('D', '3', $this->obj4))
                    ),
                ), array(
                    'Group 1' => new ChoiceGroupView(
                        'Group 1',
                        array(1 => new ChoiceView('B', '1', $this->obj2))
                    ),
                    'Group 2' => new ChoiceGroupView(
                        'Group 2',
                        array(2 => new ChoiceView('C', '2', $this->obj3))
                    ),
                )
        ), $view);
    }
}
