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

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
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

    private $obj5;

    private $obj6;

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
            case 'e': return '0.1';
            case 'f': return '0.2';
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

    public function getLabelAttr($object)
    {
        return $object->label_attr;
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
        $this->obj5 = (object) array('label' => 'E', 'index' => 'u', 'value' => '', 'preferred' => true, 'group' => 'Group 3', 'attr' => array('label_attr2' => 'label_value2'));
        $this->obj6 = (object) array('label' => 'F', 'index' => 'v', 'value' => '', 'preferred' => false, 'group' => 'Group 3', 'attr' => array('label_attr1' => 'label_value1'));
        $this->list = new ArrayChoiceList(
            array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4, 'E' => $this->obj5, 'F' => $this->obj6)
        );
        $this->factory = new DefaultChoiceListFactory();
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
            array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4, 'E' => $this->obj5, 'F' => $this->obj6)
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    public function testCreateFromChoicesFlatTraversable()
    {
        $list = $this->factory->createListFromChoices(
            new \ArrayIterator(array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4, 'E' => $this->obj5, 'F' => $this->obj6))
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    public function testCreateFromChoicesFlatValuesAsCallable()
    {
        $list = $this->factory->createListFromChoices(
            array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4, 'E' => $this->obj5, 'F' => $this->obj6),
            array($this, 'getValue')
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromChoicesFlatValuesAsClosure()
    {
        $list = $this->factory->createListFromChoices(
            array('A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4, 'E' => $this->obj5, 'F' => $this->obj6),
            function ($object) { return $object->value; }
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromChoicesGrouped()
    {
        $list = $this->factory->createListFromChoices(
            array(
                'Group 1' => array('A' => $this->obj1, 'B' => $this->obj2),
                'Group 2' => array('C' => $this->obj3, 'D' => $this->obj4),
                'Group 3' => array('E' => $this->obj5, 'F' => $this->obj6),
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
                    'Group 3' => array('E' => $this->obj5, 'F' => $this->obj6),
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
                'Group 3' => array('E' => $this->obj5, 'F' => $this->obj6),
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
                'Group 3' => array('E' => $this->obj5, 'F' => $this->obj6),
            ),
            function ($object) { return $object->value; }
        );

        $this->assertObjectListWithCustomValues($list);
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
            array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E', 'f' => 'F')
        );

        $this->assertScalarListWithGeneratedValues($list);
    }

    public function testCreateFromFlippedChoicesFlatTraversable()
    {
        $list = $this->factory->createListFromFlippedChoices(
            new \ArrayIterator(array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E', 'f' => 'F'))
        );

        $this->assertScalarListWithGeneratedValues($list);
    }

    public function testCreateFromFlippedChoicesFlatValuesAsCallable()
    {
        $list = $this->factory->createListFromFlippedChoices(
            array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E', 'f' => 'F'),
            array($this, 'getScalarValue')
        );

        $this->assertScalarListWithCustomValues($list);
    }

    public function testCreateFromFlippedChoicesFlatValuesAsClosure()
    {
        $list = $this->factory->createListFromFlippedChoices(
            array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E', 'f' => 'F'),
            function ($choice) {
                switch ($choice) {
                    case 'a': return 'a';
                    case 'b': return 'b';
                    case 'c': return '1';
                    case 'd': return '2';
                    case 'e': return '0.1';
                    case 'f': return '0.2';
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
                'Group 3' => array('e' => 'E', 'f' => 'F'),
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
                    'Group 3' => array('e' => 'E', 'f' => 'F'),
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
                'Group 3' => array('e' => 'E', 'f' => 'F'),
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
                'Group 3' => array('e' => 'E', 'f' => 'F'),
            ),
            function ($choice) {
                switch ($choice) {
                    case 'a': return 'a';
                    case 'b': return 'b';
                    case 'c': return '1';
                    case 'd': return '2';
                    case 'e': return '0.1';
                    case 'f': return '0.2';
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

    public function testCreateViewFlat()
    {
        $view = $this->factory->createView($this->list);

        $this->assertEquals(new ChoiceListView(
                array(
                    0 => new ChoiceView('A', '0', $this->obj1),
                    1 => new ChoiceView('B', '1', $this->obj2),
                    2 => new ChoiceView('C', '2', $this->obj3),
                    3 => new ChoiceView('D', '3', $this->obj4),
                    4 => new ChoiceView('E', '4', $this->obj5),
                    5 => new ChoiceView('F', '5', $this->obj6),
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
                    4 => new ChoiceView('E', '4', $this->obj3),
                    5 => new ChoiceView('F', '5', $this->obj4),
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
        $view = $this->factory->createView(
            $this->list,
            function ($object, $key) {
                return 'B' === $key || 'C' === $key;
            }
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatPreferredChoicesClosureReceivesValue()
    {
        $view = $this->factory->createView(
            $this->list,
            function ($object, $key, $value) {
                return '1' === $value || '2' === $value;
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

    public function testCreateViewFlatLabelClosureReceivesValue()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            function ($object, $key, $value) {
                switch ($value) {
                    case '0': return 'A';
                    case '1': return 'B';
                    case '2': return 'C';
                    case '3': return 'D';
                    case '4': return 'E';
                    case '5': return 'F';
                }
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
                    case 'E': return 'u';
                    case 'F': return 'v';
                }
            }
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    public function testCreateViewFlatIndexClosureReceivesValue()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            function ($object, $key, $value) {
                switch ($value) {
                    case '0': return 'w';
                    case '1': return 'x';
                    case '2': return 'y';
                    case '3': return 'z';
                    case '4': return 'u';
                    case '5': return 'v';
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
                'Group 3' => array('E' => true, 'F' => true),
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
                'Group 3' => array('E' => true, 'F' => true),
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
                return $obj1 === $object || $obj2 === $object
                    ? 'Group 1'
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

    public function testCreateViewFlatGroupByClosureReceivesValue()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            function ($object, $key, $value) {
                return '0' === $value || '1' === $value ? 'Group 1' : 'Group 2';
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

    public function testCreateViewFlatLabelAttrAsArray()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            null, // attr
            array(
                'E' => array('label_attr1' => 'label_value1'),
                'F' => array('label_attr2' => 'label_value2')
            )
        );

        $this->assertFlatViewWithLabelAttr($view);
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

    public function testCreateViewFlatLabelAttrEmpty()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            null, // attr
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

    public function testCreateViewFlatLabelAttrAsCallable()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            null, // attr
            array($this, 'getLabelAttr')
        );

        $this->assertFlatViewWithLabelAttr($view);
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

    public function testCreateViewFlatLabelAttrAsClosure()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            null, // attr
            function ($object) {
                return $object->label_attr;
            }
        );

        $this->assertFlatViewWithLabelAttr($view);
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

    public function testCreateViewFlatLabelAttrClosureReceivesKey()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            null, // attr
            function ($object, $key) {
                switch ($key) {
                    case 'E': return array('label_attr2' => 'label_value2');
                    case 'F': return array('label_attr1' => 'label_value1');
                    default: return array();
                }
            }
        );

        $this->assertFlatViewWithLabelAttr($view);
    }

    public function testCreateViewFlatAttrClosureReceivesValue()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            function ($object, $key, $value) {
                switch ($value) {
                    case '1': return array('attr1' => 'value1');
                    case '2': return array('attr2' => 'value2');
                    default: return array();
                }
            }
        );

        $this->assertFlatViewWithAttr($view);
    }

    public function testCreateViewFlatLabelAttrClosureReceivesValue()
    {
        $view = $this->factory->createView(
            $this->list,
            array($this->obj2, $this->obj3),
            null, // label
            null, // index
            null, // group
            function ($object, $key, $value) {
                switch ($value) {
                    case '0.1': return array('label_attr1' => 'label_value1');
                    case '0.2': return array('label_attr2' => 'label_value2');
                    default: return array();
                }
            }
        );

        $this->assertFlatViewWithLabelAttr($view);
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
            'E' => 'e',
            'F' => 'f',
        ), $list->getChoices());

        $this->assertSame(array(
            'A' => 'a',
            'B' => 'b',
            'C' => 'c',
            'D' => 'd',
            'E' => 'e',
            'F' => 'f',
        ), $list->getValues());
    }

    private function assertObjectListWithGeneratedValues(ChoiceListInterface $list)
    {
        $this->assertSame(array(
            'A' => $this->obj1,
            'B' => $this->obj2,
            'C' => $this->obj3,
            'D' => $this->obj4,
            'E' => $this->obj5,
            'F' => $this->obj6,
        ), $list->getChoices());

        $this->assertSame(array(
            'A' => '0',
            'B' => '1',
            'C' => '2',
            'D' => '3',
            'E' => '4',
            'F' => '5',
        ), $list->getValues());
    }

    private function assertScalarListWithCustomValues(ChoiceListInterface $list)
    {
        $this->assertSame(array(
            'A' => 'a',
            'B' => 'b',
            'C' => 'c',
            'D' => 'd',
            'E' => 'e',
            'F' => 'f',
        ), $list->getChoices());

        $this->assertSame(array(
            'A' => 'a',
            'B' => 'b',
            'C' => '1',
            'D' => '2',
            'E' => '0.1',
            'F' => '0.2',
        ), $list->getValues());
    }

    private function assertObjectListWithCustomValues(ChoiceListInterface $list)
    {
        $this->assertSame(array(
            'A' => $this->obj1,
            'B' => $this->obj2,
            'C' => $this->obj3,
            'D' => $this->obj4,
            'E' => $this->obj5,
            'F' => $this->obj6,
        ), $list->getChoices());

        $this->assertSame(array(
            'A' => 'a',
            'B' => 'b',
            'C' => '1',
            'D' => '2',
            'E' => '0.1',
            'F' => '0.2',
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
                ), array(
                    5 => new ChoiceView('E', '5', $this->obj5),
                    4 => new ChoiceView('F', '4', $this->obj6),
                )
        ), $view);
    }

    private function assertFlatViewWithCustomIndices($view)
    {
        $this->assertEquals(new ChoiceListView(
                array(
                    'w' => new ChoiceView('A', '0',   $this->obj1),
                    'z' => new ChoiceView('D', '3',   $this->obj4),
                ), array(
                    'x' => new ChoiceView('B', '1',   $this->obj2),
                    'y' => new ChoiceView('C', '2',   $this->obj3),
                ), array(
                    'u' => new ChoiceView('E', '0.1', $this->obj4),
                    'v' => new ChoiceView('F', '0.2', $this->obj5),
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
                ), array(
                5 => new ChoiceView('E', '5', $this->obj5),
                4 => new ChoiceView('F', '4', $this->obj6),
            )
        ), $view);
    }

    private function assertFlatViewWithLabelAttr($view)
    {
        $this->assertEquals(new ChoiceListView(
                array(
                    0 => new ChoiceView('A', '0', $this->obj1),
                    3 => new ChoiceView('D', '3', $this->obj4),
                ), array(
                    1 => new ChoiceView('B', '1', $this->obj2),
                    2 => new ChoiceView('C', '2', $this->obj3),
                ), array(
                    5 => new ChoiceView(
                        'E',
                        '5',
                        $this->obj5,
                        array('label_attr2' => 'label_value2')
                    ),
                    4 => new ChoiceView(
                        'F',
                        '4',
                        $this->obj6,
                        array('label_attr1' => 'label_value1')
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
                ), array(
                    'Group 1' => new ChoiceGroupView(
                        'Group 2',
                        array(5 => new ChoiceView('E', '5', $this->obj5))
                    ),
                    'Group 2' => new ChoiceGroupView(
                        'Group 2',
                        array(4 => new ChoiceView('F', '4', $this->obj6))
                    ),
                )
        ), $view);
    }
}
