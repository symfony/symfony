<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;
use Symfony\Component\Form\Tests\Fixtures\FixedFilterListener;
use Symfony\Component\PropertyAccess\PropertyPath;

class SimpleFormTest_Countable implements \Countable
{
    private $count;

    public function __construct($count)
    {
        $this->count = $count;
    }

    public function count()
    {
        return $this->count;
    }
}

class SimpleFormTest_Traversable implements \IteratorAggregate
{
    private $iterator;

    public function __construct($count)
    {
        $this->iterator = new \ArrayIterator($count > 0 ? array_fill(0, $count, 'Foo') : array());
    }

    public function getIterator()
    {
        return $this->iterator;
    }
}

class SimpleFormTest extends AbstractFormTest
{
    public function testDataIsInitializedToConfiguredValue()
    {
        $model = new FixedDataTransformer(array(
            'default' => 'foo',
        ));
        $view = new FixedDataTransformer(array(
            'foo' => 'bar',
        ));

        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config->addViewTransformer($view);
        $config->addModelTransformer($model);
        $config->setData('default');
        $form = new Form($config);

        $this->assertSame('default', $form->getData());
        $this->assertSame('foo', $form->getNormData());
        $this->assertSame('bar', $form->getViewData());
    }

    /**
     * @expectedException        \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage Unable to transform value for property path "name": No mapping for value "arg"
     */
    public function testDataTransformationFailure()
    {
        $model = new FixedDataTransformer(array(
            'default' => 'foo',
        ));
        $view = new FixedDataTransformer(array(
            'foo' => 'bar',
        ));

        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config->addViewTransformer($view);
        $config->addModelTransformer($model);
        $config->setData('arg');
        $form = new Form($config);

        $form->getData();
    }

    // https://github.com/symfony/symfony/commit/d4f4038f6daf7cf88ca7c7ab089473cce5ebf7d8#commitcomment-1632879
    public function testDataIsInitializedFromSubmit()
    {
        $mock = $this->getMockBuilder('\stdClass')
            ->setMethods(array('preSetData', 'preSubmit'))
            ->getMock();
        $mock->expects($this->at(0))
            ->method('preSetData');
        $mock->expects($this->at(1))
            ->method('preSubmit');

        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config->addEventListener(FormEvents::PRE_SET_DATA, array($mock, 'preSetData'));
        $config->addEventListener(FormEvents::PRE_SUBMIT, array($mock, 'preSubmit'));
        $form = new Form($config);

        // no call to setData() or similar where the object would be
        // initialized otherwise

        $form->submit('foobar');
    }

    // https://github.com/symfony/symfony/pull/7789
    public function testFalseIsConvertedToNull()
    {
        $mock = $this->getMockBuilder('\stdClass')
            ->setMethods(array('preSubmit'))
            ->getMock();
        $mock->expects($this->once())
            ->method('preSubmit')
            ->with($this->callback(function ($event) {
                return null === $event->getData();
            }));

        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config->addEventListener(FormEvents::PRE_SUBMIT, array($mock, 'preSubmit'));
        $form = new Form($config);

        $form->submit(false);

        $this->assertTrue($form->isValid());
        $this->assertNull($form->getData());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\AlreadySubmittedException
     */
    public function testSubmitThrowsExceptionIfAlreadySubmitted()
    {
        $this->form->submit(array());
        $this->form->submit(array());
    }

    public function testSubmitIsIgnoredIfDisabled()
    {
        $form = $this->getBuilder()
            ->setDisabled(true)
            ->setData('initial')
            ->getForm();

        $form->submit('new');

        $this->assertEquals('initial', $form->getData());
        $this->assertTrue($form->isSubmitted());
    }

    public function testNeverRequiredIfParentNotRequired()
    {
        $parent = $this->getBuilder()->setRequired(false)->getForm();
        $child = $this->getBuilder()->setRequired(true)->getForm();

        $child->setParent($parent);

        $this->assertFalse($child->isRequired());
    }

    public function testRequired()
    {
        $parent = $this->getBuilder()->setRequired(true)->getForm();
        $child = $this->getBuilder()->setRequired(true)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isRequired());
    }

    public function testNotRequired()
    {
        $parent = $this->getBuilder()->setRequired(true)->getForm();
        $child = $this->getBuilder()->setRequired(false)->getForm();

        $child->setParent($parent);

        $this->assertFalse($child->isRequired());
    }

    /**
     * @dataProvider getDisabledStates
     */
    public function testAlwaysDisabledIfParentDisabled($parentDisabled, $disabled, $result)
    {
        $parent = $this->getBuilder()->setDisabled($parentDisabled)->getForm();
        $child = $this->getBuilder()->setDisabled($disabled)->getForm();

        $child->setParent($parent);

        $this->assertSame($result, $child->isDisabled());
    }

    public function getDisabledStates()
    {
        return array(
            // parent, button, result
            array(true, true, true),
            array(true, false, true),
            array(false, true, true),
            array(false, false, false),
        );
    }

    public function testGetRootReturnsRootOfParent()
    {
        $parent = $this->getMockForm();
        $parent->expects($this->once())
            ->method('getRoot')
            ->will($this->returnValue('ROOT'));

        $this->form->setParent($parent);

        $this->assertEquals('ROOT', $this->form->getRoot());
    }

    public function testGetRootReturnsSelfIfNoParent()
    {
        $this->assertSame($this->form, $this->form->getRoot());
    }

    public function testEmptyIfEmptyArray()
    {
        $this->form->setData(array());

        $this->assertTrue($this->form->isEmpty());
    }

    public function testEmptyIfEmptyCountable()
    {
        $this->form = new Form(new FormConfigBuilder('name', __NAMESPACE__.'\SimpleFormTest_Countable', $this->dispatcher));

        $this->form->setData(new SimpleFormTest_Countable(0));

        $this->assertTrue($this->form->isEmpty());
    }

    public function testNotEmptyIfFilledCountable()
    {
        $this->form = new Form(new FormConfigBuilder('name', __NAMESPACE__.'\SimpleFormTest_Countable', $this->dispatcher));

        $this->form->setData(new SimpleFormTest_Countable(1));

        $this->assertFalse($this->form->isEmpty());
    }

    public function testEmptyIfEmptyTraversable()
    {
        $this->form = new Form(new FormConfigBuilder('name', __NAMESPACE__.'\SimpleFormTest_Traversable', $this->dispatcher));

        $this->form->setData(new SimpleFormTest_Traversable(0));

        $this->assertTrue($this->form->isEmpty());
    }

    public function testNotEmptyIfFilledTraversable()
    {
        $this->form = new Form(new FormConfigBuilder('name', __NAMESPACE__.'\SimpleFormTest_Traversable', $this->dispatcher));

        $this->form->setData(new SimpleFormTest_Traversable(1));

        $this->assertFalse($this->form->isEmpty());
    }

    public function testEmptyIfNull()
    {
        $this->form->setData(null);

        $this->assertTrue($this->form->isEmpty());
    }

    public function testEmptyIfEmptyString()
    {
        $this->form->setData('');

        $this->assertTrue($this->form->isEmpty());
    }

    public function testNotEmptyIfText()
    {
        $this->form->setData('foobar');

        $this->assertFalse($this->form->isEmpty());
    }

    public function testValidIfSubmitted()
    {
        $form = $this->getBuilder()->getForm();
        $form->submit('foobar');

        $this->assertTrue($form->isValid());
    }

    public function testValidIfSubmittedAndDisabled()
    {
        $form = $this->getBuilder()->setDisabled(true)->getForm();
        $form->submit('foobar');

        $this->assertTrue($form->isValid());
    }

    public function testNotValidIfNotSubmitted()
    {
        $this->assertFalse($this->form->isValid());
    }

    public function testNotValidIfErrors()
    {
        $form = $this->getBuilder()->getForm();
        $form->submit('foobar');
        $form->addError(new FormError('Error!'));

        $this->assertFalse($form->isValid());
    }

    public function testHasErrors()
    {
        $this->form->addError(new FormError('Error!'));

        $this->assertCount(1, $this->form->getErrors());
    }

    public function testHasNoErrors()
    {
        $this->assertCount(0, $this->form->getErrors());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\AlreadySubmittedException
     */
    public function testSetParentThrowsExceptionIfAlreadySubmitted()
    {
        $this->form->submit(array());
        $this->form->setParent($this->getBuilder('parent')->getForm());
    }

    public function testSubmitted()
    {
        $form = $this->getBuilder()->getForm();
        $form->submit('foobar');

        $this->assertTrue($form->isSubmitted());
    }

    public function testNotSubmitted()
    {
        $this->assertFalse($this->form->isSubmitted());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\AlreadySubmittedException
     */
    public function testSetDataThrowsExceptionIfAlreadySubmitted()
    {
        $this->form->submit(array());
        $this->form->setData(null);
    }

    public function testSetDataClonesObjectIfNotByReference()
    {
        $data = new \stdClass();
        $form = $this->getBuilder('name', null, '\stdClass')->setByReference(false)->getForm();
        $form->setData($data);

        $this->assertNotSame($data, $form->getData());
        $this->assertEquals($data, $form->getData());
    }

    public function testSetDataDoesNotCloneObjectIfByReference()
    {
        $data = new \stdClass();
        $form = $this->getBuilder('name', null, '\stdClass')->setByReference(true)->getForm();
        $form->setData($data);

        $this->assertSame($data, $form->getData());
    }

    public function testSetDataExecutesTransformationChain()
    {
        // use real event dispatcher now
        $form = $this->getBuilder('name', new EventDispatcher())
            ->addEventSubscriber(new FixedFilterListener(array(
                'preSetData' => array(
                    'app' => 'filtered',
                ),
            )))
            ->addModelTransformer(new FixedDataTransformer(array(
                '' => '',
                'filtered' => 'norm',
            )))
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'norm' => 'client',
            )))
            ->getForm();

        $form->setData('app');

        $this->assertEquals('filtered', $form->getData());
        $this->assertEquals('norm', $form->getNormData());
        $this->assertEquals('client', $form->getViewData());
    }

    public function testSetDataExecutesViewTransformersInOrder()
    {
        $form = $this->getBuilder()
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'first' => 'second',
            )))
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'second' => 'third',
            )))
            ->getForm();

        $form->setData('first');

        $this->assertEquals('third', $form->getViewData());
    }

    public function testSetDataExecutesModelTransformersInReverseOrder()
    {
        $form = $this->getBuilder()
            ->addModelTransformer(new FixedDataTransformer(array(
                '' => '',
                'second' => 'third',
            )))
            ->addModelTransformer(new FixedDataTransformer(array(
                '' => '',
                'first' => 'second',
            )))
            ->getForm();

        $form->setData('first');

        $this->assertEquals('third', $form->getNormData());
    }

    /*
     * When there is no data transformer, the data must have the same format
     * in all three representations
     */
    public function testSetDataConvertsScalarToStringIfNoTransformer()
    {
        $form = $this->getBuilder()->getForm();

        $form->setData(1);

        $this->assertSame('1', $form->getData());
        $this->assertSame('1', $form->getNormData());
        $this->assertSame('1', $form->getViewData());
    }

    /*
     * Data in client format should, if possible, always be a string to
     * facilitate differentiation between '0' and ''
     */
    public function testSetDataConvertsScalarToStringIfOnlyModelTransformer()
    {
        $form = $this->getBuilder()
            ->addModelTransformer(new FixedDataTransformer(array(
            '' => '',
            1 => 23,
        )))
            ->getForm();

        $form->setData(1);

        $this->assertSame(1, $form->getData());
        $this->assertSame(23, $form->getNormData());
        $this->assertSame('23', $form->getViewData());
    }

    /*
     * NULL remains NULL in app and norm format to remove the need to treat
     * empty values and NULL explicitly in the application
     */
    public function testSetDataConvertsNullToStringIfNoTransformer()
    {
        $form = $this->getBuilder()->getForm();

        $form->setData(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSetDataIsIgnoredIfDataIsLocked()
    {
        $form = $this->getBuilder()
            ->setData('default')
            ->setDataLocked(true)
            ->getForm();

        $form->setData('foobar');

        $this->assertSame('default', $form->getData());
    }

    public function testPreSetDataChangesDataIfDataIsLocked()
    {
        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config
            ->setData('default')
            ->setDataLocked(true)
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                $event->setData('foobar');
            });
        $form = new Form($config);

        $this->assertSame('foobar', $form->getData());
        $this->assertSame('foobar', $form->getNormData());
        $this->assertSame('foobar', $form->getViewData());
    }

    public function testSubmitConvertsEmptyToNullIfNoTransformer()
    {
        $form = $this->getBuilder()->getForm();

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitExecutesTransformationChain()
    {
        // use real event dispatcher now
        $form = $this->getBuilder('name', new EventDispatcher())
            ->addEventSubscriber(new FixedFilterListener(array(
                'preSubmit' => array(
                    'client' => 'filteredclient',
                ),
                'onSubmit' => array(
                    'norm' => 'filterednorm',
                ),
            )))
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                // direction is reversed!
                'norm' => 'filteredclient',
                'filterednorm' => 'cleanedclient',
            )))
            ->addModelTransformer(new FixedDataTransformer(array(
                '' => '',
                // direction is reversed!
                'app' => 'filterednorm',
            )))
            ->getForm();

        $form->submit('client');

        $this->assertEquals('app', $form->getData());
        $this->assertEquals('filterednorm', $form->getNormData());
        $this->assertEquals('cleanedclient', $form->getViewData());
    }

    public function testSubmitExecutesViewTransformersInReverseOrder()
    {
        $form = $this->getBuilder()
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'third' => 'second',
            )))
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'second' => 'first',
            )))
            ->getForm();

        $form->submit('first');

        $this->assertEquals('third', $form->getNormData());
    }

    public function testSubmitExecutesModelTransformersInOrder()
    {
        $form = $this->getBuilder()
            ->addModelTransformer(new FixedDataTransformer(array(
                '' => '',
                'second' => 'first',
            )))
            ->addModelTransformer(new FixedDataTransformer(array(
                '' => '',
                'third' => 'second',
            )))
            ->getForm();

        $form->submit('first');

        $this->assertEquals('third', $form->getData());
    }

    public function testSynchronizedByDefault()
    {
        $this->assertTrue($this->form->isSynchronized());
    }

    public function testSynchronizedAfterSubmission()
    {
        $this->form->submit('foobar');

        $this->assertTrue($this->form->isSynchronized());
    }

    public function testNotSynchronizedIfViewReverseTransformationFailed()
    {
        $transformer = $this->getDataTransformer();
        $transformer->expects($this->once())
            ->method('reverseTransform')
            ->will($this->throwException(new TransformationFailedException()));

        $form = $this->getBuilder()
            ->addViewTransformer($transformer)
            ->getForm();

        $form->submit('foobar');

        $this->assertFalse($form->isSynchronized());
    }

    public function testNotSynchronizedIfModelReverseTransformationFailed()
    {
        $transformer = $this->getDataTransformer();
        $transformer->expects($this->once())
            ->method('reverseTransform')
            ->will($this->throwException(new TransformationFailedException()));

        $form = $this->getBuilder()
            ->addModelTransformer($transformer)
            ->getForm();

        $form->submit('foobar');

        $this->assertFalse($form->isSynchronized());
    }

    public function testEmptyDataCreatedBeforeTransforming()
    {
        $form = $this->getBuilder()
            ->setEmptyData('foo')
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                // direction is reversed!
                'bar' => 'foo',
            )))
            ->getForm();

        $form->submit('');

        $this->assertEquals('bar', $form->getData());
    }

    public function testEmptyDataFromClosure()
    {
        $test = $this;
        $form = $this->getBuilder()
            ->setEmptyData(function ($form) use ($test) {
                // the form instance is passed to the closure to allow use
                // of form data when creating the empty value
                $test->assertInstanceOf('Symfony\Component\Form\FormInterface', $form);

                return 'foo';
            })
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                // direction is reversed!
                'bar' => 'foo',
            )))
            ->getForm();

        $form->submit('');

        $this->assertEquals('bar', $form->getData());
    }

    public function testSubmitResetsErrors()
    {
        $this->form->addError(new FormError('Error!'));
        $this->form->submit('foobar');

        $this->assertCount(0, $this->form->getErrors());
    }

    public function testCreateView()
    {
        $type = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeInterface')->getMock();
        $view = $this->getMockBuilder('Symfony\Component\Form\FormView')->getMock();
        $form = $this->getBuilder()->setType($type)->getForm();

        $type->expects($this->once())
            ->method('createView')
            ->with($form)
            ->will($this->returnValue($view));

        $this->assertSame($view, $form->createView());
    }

    public function testCreateViewWithParent()
    {
        $type = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeInterface')->getMock();
        $view = $this->getMockBuilder('Symfony\Component\Form\FormView')->getMock();
        $parentForm = $this->getMockBuilder('Symfony\Component\Form\Test\FormInterface')->getMock();
        $parentView = $this->getMockBuilder('Symfony\Component\Form\FormView')->getMock();
        $form = $this->getBuilder()->setType($type)->getForm();
        $form->setParent($parentForm);

        $parentForm->expects($this->once())
            ->method('createView')
            ->will($this->returnValue($parentView));

        $type->expects($this->once())
            ->method('createView')
            ->with($form, $parentView)
            ->will($this->returnValue($view));

        $this->assertSame($view, $form->createView());
    }

    public function testCreateViewWithExplicitParent()
    {
        $type = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeInterface')->getMock();
        $view = $this->getMockBuilder('Symfony\Component\Form\FormView')->getMock();
        $parentView = $this->getMockBuilder('Symfony\Component\Form\FormView')->getMock();
        $form = $this->getBuilder()->setType($type)->getForm();

        $type->expects($this->once())
            ->method('createView')
            ->with($form, $parentView)
            ->will($this->returnValue($view));

        $this->assertSame($view, $form->createView($parentView));
    }

    /**
     * @group legacy
     */
    public function testGetErrorsAsString()
    {
        $this->form->addError(new FormError('Error!'));

        $this->assertEquals("ERROR: Error!\n", $this->form->getErrorsAsString());
    }

    public function testFormCanHaveEmptyName()
    {
        $form = $this->getBuilder('')->getForm();

        $this->assertEquals('', $form->getName());
    }

    public function testSetNullParentWorksWithEmptyName()
    {
        $form = $this->getBuilder('')->getForm();
        $form->setParent(null);

        $this->assertNull($form->getParent());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\LogicException
     * @expectedExceptionMessage A form with an empty name cannot have a parent form.
     */
    public function testFormCannotHaveEmptyNameNotInRootLevel()
    {
        $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->add($this->getBuilder(''))
            ->getForm();
    }

    public function testGetPropertyPathReturnsConfiguredPath()
    {
        $form = $this->getBuilder()->setPropertyPath('address.street')->getForm();

        $this->assertEquals(new PropertyPath('address.street'), $form->getPropertyPath());
    }

    // see https://github.com/symfony/symfony/issues/3903
    public function testGetPropertyPathDefaultsToNameIfParentHasDataClass()
    {
        $parent = $this->getBuilder(null, null, 'stdClass')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getBuilder('name')->getForm();
        $parent->add($form);

        $this->assertEquals(new PropertyPath('name'), $form->getPropertyPath());
    }

    // see https://github.com/symfony/symfony/issues/3903
    public function testGetPropertyPathDefaultsToIndexedNameIfParentDataClassIsNull()
    {
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getBuilder('name')->getForm();
        $parent->add($form);

        $this->assertEquals(new PropertyPath('[name]'), $form->getPropertyPath());
    }

    public function testGetPropertyPathDefaultsToNameIfFirstParentWithoutInheritDataHasDataClass()
    {
        $grandParent = $this->getBuilder(null, null, 'stdClass')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setInheritData(true)
            ->getForm();
        $form = $this->getBuilder('name')->getForm();
        $grandParent->add($parent);
        $parent->add($form);

        $this->assertEquals(new PropertyPath('name'), $form->getPropertyPath());
    }

    public function testGetPropertyPathDefaultsToIndexedNameIfDataClassOfFirstParentWithoutInheritDataIsNull()
    {
        $grandParent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setInheritData(true)
            ->getForm();
        $form = $this->getBuilder('name')->getForm();
        $grandParent->add($parent);
        $parent->add($form);

        $this->assertEquals(new PropertyPath('[name]'), $form->getPropertyPath());
    }

    public function testViewDataMayBeObjectIfDataClassIsNull()
    {
        $object = new \stdClass();
        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'foo' => $object,
        )));
        $form = new Form($config);

        $form->setData('foo');

        $this->assertSame($object, $form->getViewData());
    }

    public function testViewDataMayBeArrayAccessIfDataClassIsNull()
    {
        $arrayAccess = $this->getMockBuilder('\ArrayAccess')->getMock();
        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'foo' => $arrayAccess,
        )));
        $form = new Form($config);

        $form->setData('foo');

        $this->assertSame($arrayAccess, $form->getViewData());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\LogicException
     */
    public function testViewDataMustBeObjectIfDataClassIsSet()
    {
        $config = new FormConfigBuilder('name', 'stdClass', $this->dispatcher);
        $config->addViewTransformer(new FixedDataTransformer(array(
            '' => '',
            'foo' => array('bar' => 'baz'),
        )));
        $form = new Form($config);

        $form->setData('foo');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     * @expectedExceptionMessage A cycle was detected. Listeners to the PRE_SET_DATA event must not call setData(). You should call setData() on the FormEvent object instead.
     */
    public function testSetDataCannotInvokeItself()
    {
        // Cycle detection to prevent endless loops
        $config = new FormConfigBuilder('name', 'stdClass', $this->dispatcher);
        $config->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $event->getForm()->setData('bar');
        });
        $form = new Form($config);

        $form->setData('foo');
    }

    public function testSubmittingWrongDataIsIgnored()
    {
        $called = 0;

        $child = $this->getBuilder('child', $this->dispatcher);
        $child->addEventListener(FormEvents::PRE_SUBMIT, function () use (&$called) {
            ++$called;
        });

        $parent = $this->getBuilder('parent', new EventDispatcher())
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->add($child)
            ->getForm();

        $parent->submit('not-an-array');

        $this->assertSame(0, $called, 'PRE_SUBMIT event listeners are not called for wrong data');
    }

    public function testHandleRequestForwardsToRequestHandler()
    {
        $handler = $this->getMockBuilder('Symfony\Component\Form\RequestHandlerInterface')->getMock();

        $form = $this->getBuilder()
            ->setRequestHandler($handler)
            ->getForm();

        $handler->expects($this->once())
            ->method('handleRequest')
            ->with($this->identicalTo($form), 'REQUEST');

        $this->assertSame($form, $form->handleRequest('REQUEST'));
    }

    public function testFormInheritsParentData()
    {
        $child = $this->getBuilder('child')
            ->setInheritData(true);

        $parent = $this->getBuilder('parent')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setData('foo')
            ->addModelTransformer(new FixedDataTransformer(array(
                'foo' => 'norm[foo]',
            )))
            ->addViewTransformer(new FixedDataTransformer(array(
                'norm[foo]' => 'view[foo]',
            )))
            ->add($child)
            ->getForm();

        $this->assertSame('foo', $parent->get('child')->getData());
        $this->assertSame('norm[foo]', $parent->get('child')->getNormData());
        $this->assertSame('view[foo]', $parent->get('child')->getViewData());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     */
    public function testInheritDataDisallowsSetData()
    {
        $form = $this->getBuilder()
            ->setInheritData(true)
            ->getForm();

        $form->setData('foo');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     */
    public function testGetDataRequiresParentToBeSetIfInheritData()
    {
        $form = $this->getBuilder()
            ->setInheritData(true)
            ->getForm();

        $form->getData();
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     */
    public function testGetNormDataRequiresParentToBeSetIfInheritData()
    {
        $form = $this->getBuilder()
            ->setInheritData(true)
            ->getForm();

        $form->getNormData();
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     */
    public function testGetViewDataRequiresParentToBeSetIfInheritData()
    {
        $form = $this->getBuilder()
            ->setInheritData(true)
            ->getForm();

        $form->getViewData();
    }

    public function testPostSubmitDataIsNullIfInheritData()
    {
        $test = $this;
        $form = $this->getBuilder()
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($test) {
                $test->assertNull($event->getData());
            })
            ->setInheritData(true)
            ->getForm();

        $form->submit('foo');
    }

    public function testSubmitIsNeverFiredIfInheritData()
    {
        $called = 0;
        $form = $this->getBuilder()
            ->addEventListener(FormEvents::SUBMIT, function () use (&$called) {
                ++$called;
            })
            ->setInheritData(true)
            ->getForm();

        $form->submit('foo');

        $this->assertSame(0, $called, 'The SUBMIT event is not fired when data are inherited from the parent form');
    }

    public function testInitializeSetsDefaultData()
    {
        $config = $this->getBuilder()->setData('DEFAULT')->getFormConfig();
        $form = $this->getMockBuilder('Symfony\Component\Form\Form')->setMethods(array('setData'))->setConstructorArgs(array($config))->getMock();

        $form->expects($this->once())
            ->method('setData')
            ->with($this->identicalTo('DEFAULT'));

        /* @var Form $form */
        $form->initialize();
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     */
    public function testInitializeFailsIfParent()
    {
        $parent = $this->getBuilder()->setRequired(false)->getForm();
        $child = $this->getBuilder()->setRequired(true)->getForm();

        $child->setParent($parent);

        $child->initialize();
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Custom resolver "Symfony\Component\Form\Tests\Fixtures\CustomOptionsResolver" must extend "Symfony\Component\OptionsResolver\OptionsResolver".
     */
    public function testCustomOptionsResolver()
    {
        $fooType = new Fixtures\LegacyFooType();
        $resolver = new Fixtures\CustomOptionsResolver();
        $fooType->setDefaultOptions($resolver);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     * @expectedExceptionMessage A cycle was detected. Listeners to the PRE_SET_DATA event must not call getData() if the form data has not already been set. You should call getData() on the FormEvent object instead.
     */
    public function testCannotCallGetDataInPreSetDataListenerIfDataHasNotAlreadyBeenSet()
    {
        $config = new FormConfigBuilder('name', 'stdClass', $this->dispatcher);
        $config->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $event->getForm()->getData();
        });
        $form = new Form($config);

        $form->setData('foo');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     * @expectedExceptionMessage A cycle was detected. Listeners to the PRE_SET_DATA event must not call getNormData() if the form data has not already been set.
     */
    public function testCannotCallGetNormDataInPreSetDataListener()
    {
        $config = new FormConfigBuilder('name', 'stdClass', $this->dispatcher);
        $config->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $event->getForm()->getNormData();
        });
        $form = new Form($config);

        $form->setData('foo');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     * @expectedExceptionMessage A cycle was detected. Listeners to the PRE_SET_DATA event must not call getViewData() if the form data has not already been set.
     */
    public function testCannotCallGetViewDataInPreSetDataListener()
    {
        $config = new FormConfigBuilder('name', 'stdClass', $this->dispatcher);
        $config->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $event->getForm()->getViewData();
        });
        $form = new Form($config);

        $form->setData('foo');
    }

    protected function createForm()
    {
        return $this->getBuilder()->getForm();
    }
}
