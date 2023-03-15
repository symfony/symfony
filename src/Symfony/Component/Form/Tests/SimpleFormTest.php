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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;
use Symfony\Component\Form\Tests\Fixtures\FixedFilterListener;
use Symfony\Component\Form\Tests\Fixtures\Map;
use Symfony\Component\PropertyAccess\PropertyPath;

class SimpleFormTest_Countable implements \Countable
{
    private $count;

    public function __construct($count)
    {
        $this->count = $count;
    }

    public function count(): int
    {
        return $this->count;
    }
}

class SimpleFormTest_Traversable implements \IteratorAggregate
{
    private $iterator;

    public function __construct($count)
    {
        $this->iterator = new \ArrayIterator($count > 0 ? array_fill(0, $count, 'Foo') : []);
    }

    public function getIterator(): \Traversable
    {
        return $this->iterator;
    }
}

class SimpleFormTest extends TestCase
{
    private $form;

    protected function setUp(): void
    {
        $this->form = $this->createForm();
    }

    /**
     * @dataProvider provideFormNames
     */
    public function testGetPropertyPath($name, $propertyPath)
    {
        $config = new FormConfigBuilder($name, null, new EventDispatcher());
        $form = new Form($config);

        $this->assertEquals($propertyPath, $form->getPropertyPath());
    }

    public static function provideFormNames()
    {
        yield [null, null];
        yield ['', null];
        yield ['0', new PropertyPath('0')];
        yield [0, new PropertyPath('0')];
        yield ['name', new PropertyPath('name')];
    }

    public function testDataIsInitializedToConfiguredValue()
    {
        $model = new FixedDataTransformer([
            'default' => 'foo',
        ]);
        $view = new FixedDataTransformer([
            'foo' => 'bar',
        ]);

        $config = new FormConfigBuilder('name', null, new EventDispatcher());
        $config->addViewTransformer($view);
        $config->addModelTransformer($model);
        $config->setData('default');
        $form = new Form($config);

        $this->assertSame('default', $form->getData());
        $this->assertSame('foo', $form->getNormData());
        $this->assertSame('bar', $form->getViewData());
    }

    public function testDataTransformationFailure()
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('Unable to transform data for property path "name": No mapping for value "arg"');
        $model = new FixedDataTransformer([
            'default' => 'foo',
        ]);
        $view = new FixedDataTransformer([
            'foo' => 'bar',
        ]);

        $config = new FormConfigBuilder('name', null, new EventDispatcher());
        $config->addViewTransformer($view);
        $config->addModelTransformer($model);
        $config->setData('arg');
        $form = new Form($config);

        $form->getData();
    }

    // https://github.com/symfony/symfony/commit/d4f4038f6daf7cf88ca7c7ab089473cce5ebf7d8#commitcomment-1632879
    public function testDataIsInitializedFromSubmit()
    {
        $preSetData = false;
        $preSubmit = false;

        $preSetDataListener = static function () use (&$preSetData, &$preSubmit): void {
            $preSetData = !$preSubmit;
        };
        $preSubmitListener = static function () use (&$preSetData, &$preSubmit): void {
            $preSubmit = $preSetData;
        };

        $config = new FormConfigBuilder('name', null, new EventDispatcher());
        $config->addEventListener(FormEvents::PRE_SET_DATA, $preSetDataListener);
        $config->addEventListener(FormEvents::PRE_SUBMIT, $preSubmitListener);
        $form = new Form($config);

        // no call to setData() or similar where the object would be
        // initialized otherwise

        $form->submit('foobar');

        $this->assertTrue($preSetData);
        $this->assertTrue($preSubmit);
    }

    // https://github.com/symfony/symfony/pull/7789
    public function testFalseIsConvertedToNull()
    {
        $passedDataIsNull = false;

        $config = new FormConfigBuilder('name', null, new EventDispatcher());
        $config->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use (&$passedDataIsNull): void {
            $passedDataIsNull = null === $event->getData();
        });
        $form = new Form($config);

        $form->submit(false);

        $this->assertTrue($passedDataIsNull);
        $this->assertTrue($form->isValid());
        $this->assertNull($form->getData());
    }

    public function testSubmitThrowsExceptionIfAlreadySubmitted()
    {
        $this->expectException(AlreadySubmittedException::class);
        $this->form->submit([]);
        $this->form->submit([]);
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

    public static function getDisabledStates()
    {
        return [
            // parent, button, result
            [true, true, true],
            [true, false, true],
            [false, true, true],
            [false, false, false],
        ];
    }

    public function testGetRootReturnsRootOfParent()
    {
        $root = $this->createForm();

        $parent = $this->createForm();
        $parent->setParent($root);

        $this->form->setParent($parent);

        $this->assertSame($root, $this->form->getRoot());
    }

    public function testGetRootReturnsSelfIfNoParent()
    {
        $this->assertSame($this->form, $this->form->getRoot());
    }

    public function testEmptyIfEmptyArray()
    {
        $this->form->setData([]);

        $this->assertTrue($this->form->isEmpty());
    }

    public function testEmptyIfEmptyCountable()
    {
        $this->form = new Form(new FormConfigBuilder('name', SimpleFormTest_Countable::class, new EventDispatcher()));

        $this->form->setData(new SimpleFormTest_Countable(0));

        $this->assertTrue($this->form->isEmpty());
    }

    public function testNotEmptyIfFilledCountable()
    {
        $this->form = new Form(new FormConfigBuilder('name', SimpleFormTest_Countable::class, new EventDispatcher()));

        $this->form->setData(new SimpleFormTest_Countable(1));

        $this->assertFalse($this->form->isEmpty());
    }

    public function testEmptyIfEmptyTraversable()
    {
        $this->form = new Form(new FormConfigBuilder('name', SimpleFormTest_Traversable::class, new EventDispatcher()));

        $this->form->setData(new SimpleFormTest_Traversable(0));

        $this->assertTrue($this->form->isEmpty());
    }

    public function testNotEmptyIfFilledTraversable()
    {
        $this->form = new Form(new FormConfigBuilder('name', SimpleFormTest_Traversable::class, new EventDispatcher()));

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

    public function testSetParentThrowsExceptionIfAlreadySubmitted()
    {
        $this->expectException(AlreadySubmittedException::class);
        $this->form->submit([]);
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

    public function testSetDataThrowsExceptionIfAlreadySubmitted()
    {
        $this->expectException(AlreadySubmittedException::class);
        $this->form->submit([]);
        $this->form->setData(null);
    }

    public function testSetDataClonesObjectIfNotByReference()
    {
        $data = new \stdClass();
        $form = $this->getBuilder('name', \stdClass::class)->setByReference(false)->getForm();
        $form->setData($data);

        $this->assertNotSame($data, $form->getData());
        $this->assertEquals($data, $form->getData());
    }

    public function testSetDataDoesNotCloneObjectIfByReference()
    {
        $data = new \stdClass();
        $form = $this->getBuilder('name', \stdClass::class)->setByReference(true)->getForm();
        $form->setData($data);

        $this->assertSame($data, $form->getData());
    }

    public function testSetDataExecutesTransformationChain()
    {
        // use real event dispatcher now
        $form = $this->getBuilder('name')
            ->addEventSubscriber(new FixedFilterListener([
                'preSetData' => [
                    'app' => 'filtered',
                ],
            ]))
            ->addModelTransformer(new FixedDataTransformer([
                '' => '',
                'filtered' => 'norm',
            ]))
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'norm' => 'client',
            ]))
            ->getForm();

        $form->setData('app');

        $this->assertEquals('filtered', $form->getData());
        $this->assertEquals('norm', $form->getNormData());
        $this->assertEquals('client', $form->getViewData());
    }

    public function testSetDataExecutesViewTransformersInOrder()
    {
        $form = $this->getBuilder()
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'first' => 'second',
            ]))
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'second' => 'third',
            ]))
            ->getForm();

        $form->setData('first');

        $this->assertEquals('third', $form->getViewData());
    }

    public function testSetDataExecutesModelTransformersInReverseOrder()
    {
        $form = $this->getBuilder()
            ->addModelTransformer(new FixedDataTransformer([
                '' => '',
                'second' => 'third',
            ]))
            ->addModelTransformer(new FixedDataTransformer([
                '' => '',
                'first' => 'second',
            ]))
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
            ->addModelTransformer(new FixedDataTransformer([
            '' => '',
            1 => 23,
        ]))
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
        $config = new FormConfigBuilder('name', null, new EventDispatcher());
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
        $form = $this->getBuilder('name')
            ->addEventSubscriber(new FixedFilterListener([
                'preSubmit' => [
                    'client' => 'filteredclient',
                ],
                'onSubmit' => [
                    'norm' => 'filterednorm',
                ],
            ]))
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                // direction is reversed!
                'norm' => 'filteredclient',
                'filterednorm' => 'cleanedclient',
            ]))
            ->addModelTransformer(new FixedDataTransformer([
                '' => '',
                // direction is reversed!
                'app' => 'filterednorm',
            ]))
            ->getForm();

        $form->submit('client');

        $this->assertEquals('app', $form->getData());
        $this->assertEquals('filterednorm', $form->getNormData());
        $this->assertEquals('cleanedclient', $form->getViewData());
    }

    public function testSubmitExecutesViewTransformersInReverseOrder()
    {
        $form = $this->getBuilder()
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'third' => 'second',
            ]))
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'second' => 'first',
            ]))
            ->getForm();

        $form->submit('first');

        $this->assertEquals('third', $form->getNormData());
    }

    public function testSubmitExecutesModelTransformersInOrder()
    {
        $form = $this->getBuilder()
            ->addModelTransformer(new FixedDataTransformer([
                '' => '',
                'second' => 'first',
            ]))
            ->addModelTransformer(new FixedDataTransformer([
                '' => '',
                'third' => 'second',
            ]))
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
        $form = $this->getBuilder()
            ->addViewTransformer(new FixedDataTransformer(['' => '']))
            ->getForm();

        $form->submit('foobar');

        $this->assertFalse($form->isSynchronized());
    }

    public function testNotSynchronizedIfModelReverseTransformationFailed()
    {
        $form = $this->getBuilder()
            ->addModelTransformer(new FixedDataTransformer(['' => '']))
            ->getForm();

        $form->submit('foobar');

        $this->assertFalse($form->isSynchronized());
    }

    public function testEmptyDataCreatedBeforeTransforming()
    {
        $form = $this->getBuilder()
            ->setEmptyData('foo')
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                // direction is reversed!
                'bar' => 'foo',
            ]))
            ->getForm();

        $form->submit('');

        $this->assertEquals('bar', $form->getData());
    }

    public function testEmptyDataFromClosure()
    {
        $form = $this->getBuilder()
            ->setEmptyData(function ($form) {
                // the form instance is passed to the closure to allow use
                // of form data when creating the empty value
                $this->assertInstanceOf(FormInterface::class, $form);

                return 'foo';
            })
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                // direction is reversed!
                'bar' => 'foo',
            ]))
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
        $type = $this->createMock(ResolvedFormTypeInterface::class);
        $view = new FormView();
        $form = $this->getBuilder()->setType($type)->getForm();

        $type->expects($this->once())
            ->method('createView')
            ->with($form)
            ->willReturn($view);

        $this->assertSame($view, $form->createView());
    }

    public function testCreateViewWithParent()
    {
        $type = $this->createMock(ResolvedFormTypeInterface::class);
        $view = new FormView();
        $parentType = $this->createMock(ResolvedFormTypeInterface::class);
        $parentForm = $this->getBuilder()->setType($parentType)->getForm();
        $parentView = new FormView();
        $form = $this->getBuilder()->setType($type)->getForm();
        $form->setParent($parentForm);

        $parentType->expects($this->once())
            ->method('createView')
            ->willReturn($parentView);

        $type->expects($this->once())
            ->method('createView')
            ->with($form, $parentView)
            ->willReturn($view);

        $this->assertSame($view, $form->createView());
    }

    public function testCreateViewWithExplicitParent()
    {
        $type = $this->createMock(ResolvedFormTypeInterface::class);
        $view = new FormView();
        $parentView = new FormView();
        $form = $this->getBuilder()->setType($type)->getForm();

        $type->expects($this->once())
            ->method('createView')
            ->with($form, $parentView)
            ->willReturn($view);

        $this->assertSame($view, $form->createView($parentView));
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

    public function testFormCannotHaveEmptyNameNotInRootLevel()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A form with an empty name cannot have a parent form.');
        $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
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
        $parent = $this->getBuilder(null, \stdClass::class)
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
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
            ->setDataMapper(new DataMapper())
            ->getForm();
        $form = $this->getBuilder('name')->getForm();
        $parent->add($form);

        $this->assertEquals(new PropertyPath('[name]'), $form->getPropertyPath());
    }

    public function testGetPropertyPathDefaultsToNameIfFirstParentWithoutInheritDataHasDataClass()
    {
        $grandParent = $this->getBuilder(null, \stdClass::class)
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->getForm();
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
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
            ->setDataMapper(new DataMapper())
            ->getForm();
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
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
        $config = new FormConfigBuilder('name', null, new EventDispatcher());
        $config->addViewTransformer(new FixedDataTransformer([
            '' => '',
            'foo' => $object,
        ]));
        $form = new Form($config);

        $form->setData('foo');

        $this->assertSame($object, $form->getViewData());
    }

    public function testViewDataMayBeArrayAccessIfDataClassIsNull()
    {
        $arrayAccess = new Map();
        $config = new FormConfigBuilder('name', null, new EventDispatcher());
        $config->addViewTransformer(new FixedDataTransformer([
            '' => '',
            'foo' => $arrayAccess,
        ]));
        $form = new Form($config);

        $form->setData('foo');

        $this->assertSame($arrayAccess, $form->getViewData());
    }

    public function testViewDataMustBeObjectIfDataClassIsSet()
    {
        $this->expectException(LogicException::class);
        $config = new FormConfigBuilder('name', 'stdClass', new EventDispatcher());
        $config->addViewTransformer(new FixedDataTransformer([
            '' => '',
            'foo' => ['bar' => 'baz'],
        ]));
        $form = new Form($config);

        $form->setData('foo');
    }

    public function testSetDataCannotInvokeItself()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A cycle was detected. Listeners to the PRE_SET_DATA event must not call setData(). You should call setData() on the FormEvent object instead.');
        // Cycle detection to prevent endless loops
        $config = new FormConfigBuilder('name', 'stdClass', new EventDispatcher());
        $config->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $event->getForm()->setData('bar');
        });
        $form = new Form($config);

        $form->setData('foo');
    }

    public function testSubmittingWrongDataIsIgnored()
    {
        $called = 0;

        $child = $this->getBuilder('child');
        $child->addEventListener(FormEvents::PRE_SUBMIT, function () use (&$called) {
            ++$called;
        });

        $parent = $this->getBuilder('parent')
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->add($child)
            ->getForm();

        $parent->submit('not-an-array');

        $this->assertSame(0, $called, 'PRE_SUBMIT event listeners are not called for wrong data');
    }

    public function testHandleRequestForwardsToRequestHandler()
    {
        $handler = $this->createMock(RequestHandlerInterface::class);

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
        $nameForm = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->setInheritData(true)
            ->getForm();
        $nameForm->add($firstNameForm = $this->getBuilder('firstName')->getForm());
        $nameForm->add($lastNameForm = $this->getBuilder('lastName')->getForm());

        $rootForm = $this->getBuilder('')
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->getForm();
        $rootForm->add($nameForm);
        $rootForm->setData(['firstName' => 'Christian', 'lastName' => 'Flothmann']);

        $this->assertSame('Christian', $firstNameForm->getData());
        $this->assertSame('Christian', $firstNameForm->getNormData());
        $this->assertSame('Christian', $firstNameForm->getViewData());
        $this->assertSame('Flothmann', $lastNameForm->getData());
        $this->assertSame('Flothmann', $lastNameForm->getNormData());
        $this->assertSame('Flothmann', $lastNameForm->getViewData());
    }

    public function testInheritDataDisallowsSetData()
    {
        $this->expectException(RuntimeException::class);
        $form = $this->getBuilder()
            ->setInheritData(true)
            ->getForm();

        $form->setData('foo');
    }

    public function testGetDataRequiresParentToBeSetIfInheritData()
    {
        $this->expectException(RuntimeException::class);
        $form = $this->getBuilder()
            ->setInheritData(true)
            ->getForm();

        $form->getData();
    }

    public function testGetNormDataRequiresParentToBeSetIfInheritData()
    {
        $this->expectException(RuntimeException::class);
        $form = $this->getBuilder()
            ->setInheritData(true)
            ->getForm();

        $form->getNormData();
    }

    public function testGetViewDataRequiresParentToBeSetIfInheritData()
    {
        $this->expectException(RuntimeException::class);
        $form = $this->getBuilder()
            ->setInheritData(true)
            ->getForm();

        $form->getViewData();
    }

    public function testPostSubmitDataIsNullIfInheritData()
    {
        $form = $this->getBuilder()
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
                $this->assertNull($event->getData());
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
        $form = new Form($config);

        /* @var Form $form */
        $form->initialize();

        $this->assertSame('DEFAULT', $form->getData());
    }

    public function testInitializeFailsIfParent()
    {
        $this->expectException(RuntimeException::class);
        $parent = $this->getBuilder()->setRequired(false)->getForm();
        $child = $this->getBuilder()->setRequired(true)->getForm();

        $child->setParent($parent);

        $child->initialize();
    }

    public function testCannotCallGetDataInPreSetDataListenerIfDataHasNotAlreadyBeenSet()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A cycle was detected. Listeners to the PRE_SET_DATA event must not call getData() if the form data has not already been set. You should call getData() on the FormEvent object instead.');
        $config = new FormConfigBuilder('name', 'stdClass', new EventDispatcher());
        $config->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $event->getForm()->getData();
        });
        $form = new Form($config);

        $form->setData('foo');
    }

    public function testCannotCallGetNormDataInPreSetDataListener()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A cycle was detected. Listeners to the PRE_SET_DATA event must not call getNormData() if the form data has not already been set.');
        $config = new FormConfigBuilder('name', 'stdClass', new EventDispatcher());
        $config->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $event->getForm()->getNormData();
        });
        $form = new Form($config);

        $form->setData('foo');
    }

    public function testCannotCallGetViewDataInPreSetDataListener()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A cycle was detected. Listeners to the PRE_SET_DATA event must not call getViewData() if the form data has not already been set.');
        $config = new FormConfigBuilder('name', 'stdClass', new EventDispatcher());
        $config->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $event->getForm()->getViewData();
        });
        $form = new Form($config);

        $form->setData('foo');
    }

    public function testIsEmptyCallback()
    {
        $config = new FormConfigBuilder('foo', null, new EventDispatcher());

        $config->setIsEmptyCallback(fn ($modelData): bool => 'ccc' === $modelData);
        $form = new Form($config);
        $form->setData('ccc');
        $this->assertTrue($form->isEmpty());

        $config->setIsEmptyCallback(fn (): bool => false);
        $form = new Form($config);
        $form->setData(null);
        $this->assertFalse($form->isEmpty());
    }

    private function createForm(): FormInterface
    {
        return $this->getBuilder()->getForm();
    }

    private function getBuilder(?string $name = 'name', string $dataClass = null, array $options = []): FormBuilder
    {
        return new FormBuilder($name, $dataClass, new EventDispatcher(), new FormFactory(new FormRegistry([], new ResolvedFormTypeFactory())), $options);
    }
}
