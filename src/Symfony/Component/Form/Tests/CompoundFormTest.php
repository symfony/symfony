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
use Symfony\Component\Form\Extension\Core\DataAccessor\PropertyPathAccessor;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\Form\SubmitButtonBuilder;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;
use Symfony\Component\Form\Tests\Fixtures\Map;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;

class CompoundFormTest extends TestCase
{
    /**
     * @var FormFactoryInterface
     */
    private $factory;

    /**
     * @var FormInterface
     */
    private $form;

    protected function setUp(): void
    {
        $this->factory = new FormFactory(new FormRegistry([], new ResolvedFormTypeFactory()));
        $this->form = $this->createForm();
    }

    public function testValidIfAllChildrenAreValid()
    {
        $this->form->add($this->getBuilder('firstName')->getForm());
        $this->form->add($this->getBuilder('lastName')->getForm());

        $this->form->submit([
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ]);

        $this->assertTrue($this->form->isValid());
    }

    public function testInvalidIfChildIsInvalid()
    {
        $this->form->add($this->getBuilder('firstName')->getForm());
        $this->form->add($this->getBuilder('lastName')->getForm());

        $this->form->submit([
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ]);

        $this->form->get('lastName')->addError(new FormError('Invalid'));

        $this->assertFalse($this->form->isValid());
    }

    public function testDisabledFormsValidEvenIfChildrenInvalid()
    {
        $form = $this->getBuilder('person')
            ->setDisabled(true)
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->add($this->getBuilder('name'))
            ->getForm();

        $form->submit(['name' => 'Jacques Doe']);

        $form->get('name')->addError(new FormError('Invalid'));

        $this->assertTrue($form->isValid());
    }

    public function testSubmitForwardsNullIfNotClearMissingButValueIsExplicitlyNull()
    {
        $child = $this->createForm('firstName', false);

        $this->form->add($child);

        $this->form->submit(['firstName' => null], false);

        $this->assertNull($this->form->get('firstName')->getData());
    }

    public function testSubmitForwardsNullIfValueIsMissing()
    {
        $child = $this->createForm('firstName', false);

        $this->form->add($child);

        $this->form->submit([]);

        $this->assertNull($this->form->get('firstName')->getData());
    }

    public function testSubmitDoesNotForwardNullIfNotClearMissing()
    {
        $child = $this->createForm('firstName', false);

        $this->form->add($child);

        $this->form->submit([], false);

        $this->assertFalse($child->isSubmitted());
    }

    public function testSubmitDoesNotAddExtraFieldForNullValues()
    {
        $factory = Forms::createFormFactoryBuilder()
            ->getFormFactory();

        $child = $factory->createNamed('file', 'Symfony\Component\Form\Extension\Core\Type\FileType', null, ['auto_initialize' => false]);

        $this->form->add($child);
        $this->form->submit(['file' => null], false);

        $this->assertCount(0, $this->form->getExtraData());
    }

    public function testClearMissingFlagIsForwarded()
    {
        $personForm = $this->createForm('person');

        $firstNameForm = $this->createForm('firstName', false);
        $personForm->add($firstNameForm);

        $lastNameForm = $this->createForm('lastName', false);
        $personForm->add($lastNameForm);

        $this->form->add($personForm);

        $this->form->setData(['person' => ['lastName' => 'last name']]);

        $this->form->submit(['person' => ['firstName' => 'foo']], false);

        $this->assertTrue($firstNameForm->isSubmitted());
        $this->assertSame('foo', $firstNameForm->getData());
        $this->assertFalse($lastNameForm->isSubmitted());
        $this->assertSame('last name', $lastNameForm->getData());
    }

    public function testCloneChildren()
    {
        $child = $this->getBuilder('child')->getForm();
        $this->form->add($child);

        $clone = clone $this->form;

        $this->assertNotSame($this->form, $clone);
        $this->assertNotSame($child, $clone['child']);
        $this->assertNotSame($this->form['child'], $clone['child']);
    }

    public function testNotEmptyIfChildNotEmpty()
    {
        $child = $this->createForm('name', false);

        $this->form->setData(null);
        $this->form->add($child);
        $child->setData('foo');

        $this->assertFalse($this->form->isEmpty());
    }

    public function testAdd()
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(['foo' => $child], $this->form->all());
    }

    public function testAddUsingNameAndType()
    {
        $this->form->add('foo', TextType::class);

        $this->assertTrue($this->form->has('foo'));

        $child = $this->form->get('foo');

        $this->assertInstanceOf(TextType::class, $child->getConfig()->getType()->getInnerType());
        $this->assertSame(['foo' => $child], $this->form->all());
    }

    public function testAddUsingIntegerNameAndType()
    {
        // in order to make casting unnecessary
        $this->form->add(0, TextType::class);

        $this->assertTrue($this->form->has(0));

        $child = $this->form->get(0);

        $this->assertInstanceOf(TextType::class, $child->getConfig()->getType()->getInnerType());
        $this->assertSame([0 => $child], $this->form->all());
    }

    public function testAddWithoutType()
    {
        $this->form->add('foo');

        $this->assertTrue($this->form->has('foo'));

        $child = $this->form->get('foo');

        $this->assertInstanceOf(TextType::class, $child->getConfig()->getType()->getInnerType());
        $this->assertSame(['foo' => $child], $this->form->all());
    }

    public function testAddUsingNameButNoType()
    {
        $this->form = $this->getBuilder('name', \stdClass::class)
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->getForm();

        $this->form->add('foo');

        $this->assertTrue($this->form->has('foo'));

        $child = $this->form->get('foo');

        $this->assertInstanceOf(TextType::class, $child->getConfig()->getType()->getInnerType());
        $this->assertSame(['foo' => $child], $this->form->all());
    }

    public function testAddUsingNameButNoTypeAndOptions()
    {
        $this->form = $this->getBuilder('name', \stdClass::class)
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->getForm();

        $this->form->add('foo');

        $this->assertTrue($this->form->has('foo'));

        $child = $this->form->get('foo');

        $this->assertInstanceOf(TextType::class, $child->getConfig()->getType()->getInnerType());
        $this->assertSame(['foo' => $child], $this->form->all());
    }

    public function testAddThrowsExceptionIfAlreadySubmitted()
    {
        $this->expectException(AlreadySubmittedException::class);
        $this->form->submit([]);
        $this->form->add($this->getBuilder('foo')->getForm());
    }

    public function testRemove()
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);
        $this->form->remove('foo');

        $this->assertNull($child->getParent());
        $this->assertCount(0, $this->form);
    }

    public function testRemoveThrowsExceptionIfAlreadySubmitted()
    {
        $this->expectException(AlreadySubmittedException::class);
        $this->form->add($this->getBuilder('foo')->setCompound(false)->getForm());
        $this->form->submit(['foo' => 'bar']);
        $this->form->remove('foo');
    }

    public function testRemoveIgnoresUnknownName()
    {
        $this->form->remove('notexisting');

        $this->assertCount(0, $this->form);
    }

    public function testArrayAccess()
    {
        $child = $this->getBuilder('foo')->getForm();

        $this->form[] = $child;

        $this->assertArrayHasKey('foo', $this->form);
        $this->assertSame($child, $this->form['foo']);

        unset($this->form['foo']);

        $this->assertArrayNotHasKey('foo', $this->form);
    }

    public function testCountable()
    {
        $this->form->add($this->getBuilder('foo')->getForm());
        $this->form->add($this->getBuilder('bar')->getForm());

        $this->assertCount(2, $this->form);
    }

    public function testIterator()
    {
        $this->form->add($this->getBuilder('foo')->getForm());
        $this->form->add($this->getBuilder('bar')->getForm());

        $this->assertSame($this->form->all(), iterator_to_array($this->form));
    }

    public function testIteratorKeys()
    {
        $this->form->add($this->getBuilder('0')->getForm());
        $this->form->add($this->getBuilder('1')->getForm());

        foreach ($this->form as $key => $value) {
            $this->assertIsString($key);
        }
    }

    public function testAddMapsViewDataToFormIfInitialized()
    {
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->setData(['child' => 'foo'])
            ->getForm();

        $child = $this->getBuilder('child')->getForm();

        $this->assertNull($child->getData());

        $form->initialize();
        $form->add($child);

        $this->assertSame('foo', $child->getData());
    }

    public function testAddDoesNotMapViewDataToFormIfNotInitialized()
    {
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->getForm();

        $child = $this->getBuilder()->getForm();

        $form->add($child);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertNull($form->getViewData());
    }

    public function testAddDoesNotMapViewDataToFormIfInheritData()
    {
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->getForm();

        $child = $this->getBuilder()
            ->setInheritData(true)
            ->getForm();

        $form->initialize();
        $form->add($child);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertNull($form->getViewData());
    }

    public function testSetDataSupportsDynamicAdditionAndRemovalOfChildren()
    {
        $form = $this->getBuilder()
            ->setCompound(true)
            // We test using DataMapper on purpose. The traversal logic
            // is currently contained in InheritDataAwareIterator, but even
            // if that changes, this test should still function.
            ->setDataMapper(new DataMapper())
            ->getForm();

        $childToBeRemoved = $this->createForm('removed', false);
        $childToBeAdded = $this->createForm('added', false);
        $child = $this->getBuilder('child')
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($form, $childToBeAdded) {
                $form->remove('removed');
                $form->add($childToBeAdded);
            })
            ->getForm();

        $form->add($child);
        $form->add($childToBeRemoved);

        // pass NULL to all children
        $form->setData([]);

        $this->assertFalse($form->has('removed'));
        $this->assertTrue($form->has('added'));
    }

    public function testSetDataMapsViewDataToChildren()
    {
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->getForm());

        $this->assertNull($child1->getData());
        $this->assertNull($child2->getData());

        $form->setData([
            'firstName' => 'foo',
            'lastName' => 'bar',
        ]);

        $this->assertSame('foo', $child1->getData());
        $this->assertSame('bar', $child2->getData());
    }

    public function testSetDataDoesNotMapViewDataToChildrenWithLockedSetData()
    {
        $mapper = new DataMapper();
        $viewData = [
            'firstName' => 'Fabien',
            'lastName' => 'Pot',
        ];
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'foo' => $viewData,
            ]))
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->setData('Potencier')->setDataLocked(true)->getForm());

        $form->setData('foo');

        $this->assertSame('Fabien', $form->get('firstName')->getData());
        $this->assertSame('Potencier', $form->get('lastName')->getData());
    }

    public function testSubmitSupportsDynamicAdditionAndRemovalOfChildren()
    {
        $form = $this->form;

        $childToBeRemoved = $this->createForm('removed');
        $childToBeAdded = $this->createForm('added');
        $child = $this->getBuilder('child')
            ->addEventListener(FormEvents::PRE_SUBMIT, function () use ($form, $childToBeAdded) {
                $form->remove('removed');
                $form->add($childToBeAdded);
            })
            ->getForm();

        $this->form->add($child);
        $this->form->add($childToBeRemoved);

        // pass NULL to all children
        $this->form->submit([]);

        $this->assertFalse($childToBeRemoved->isSubmitted());
        $this->assertTrue($childToBeAdded->isSubmitted());
    }

    public function testSubmitMapsSubmittedChildrenOntoExistingViewData()
    {
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->setData([
                'firstName' => null,
                'lastName' => null,
            ])
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->setCompound(false)->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->setCompound(false)->getForm());

        $this->assertSame(['firstName' => null, 'lastName' => null], $form->getData());

        $form->submit([
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ]);

        $this->assertSame(['firstName' => 'Bernhard', 'lastName' => 'Schussek'], $form->getData());
    }

    public function testMapFormsToDataIsNotInvokedIfInheritData()
    {
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->setCompound(false)->setInheritData(true)->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->setCompound(false)->setInheritData(true)->getForm());

        $form->submit([
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ]);

        $this->assertNull($child1->getData());
        $this->assertNull($child1->getNormData());
        $this->assertNull($child1->getViewData());
        $this->assertNull($child2->getData());
        $this->assertNull($child2->getNormData());
        $this->assertNull($child2->getViewData());
    }

    /*
     * https://github.com/symfony/symfony/issues/4480
     */
    public function testSubmitRestoresViewDataIfCompoundAndEmpty()
    {
        $object = new \stdClass();
        $form = $this->getBuilder('name', \stdClass::class)
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->setData($object)
            ->getForm();

        $form->submit([]);

        $this->assertSame($object, $form->getData());
    }

    public function testSubmitMapsSubmittedChildrenOntoEmptyData()
    {
        $object = new Map();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->setEmptyData($object)
            ->setData(null)
            ->getForm();

        $form->add($child = $this->getBuilder('name')->setCompound(false)->getForm());

        $form->submit([
            'name' => 'Bernhard',
        ]);

        $this->assertSame('Bernhard', $object['name']);
    }

    public static function requestMethodProvider()
    {
        return [
            ['POST'],
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
        ];
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequest($method)
    {
        $path = tempnam(sys_get_temp_dir(), 'sf');
        touch($path);
        file_put_contents($path, 'zaza');
        $values = [
            'author' => [
                'name' => 'Bernhard',
                'image' => ['filename' => 'foobar.png'],
            ],
        ];

        $files = [
            'author' => [
                'error' => ['image' => \UPLOAD_ERR_OK],
                'name' => ['image' => 'upload.png'],
                'size' => ['image' => null],
                'tmp_name' => ['image' => $path],
                'type' => ['image' => 'image/png'],
            ],
        ];

        $request = new Request([], $values, [], [], $files, [
            'REQUEST_METHOD' => $method,
        ]);

        $form = $this->getBuilder('author')
            ->setMethod($method)
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('name')->getForm());
        $form->add($this->getBuilder('image')->getForm());

        $form->handleRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', \UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals($file, $form['image']->getData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequestWithEmptyRootFormName($method)
    {
        $path = tempnam(sys_get_temp_dir(), 'sf');
        touch($path);
        file_put_contents($path, 'zaza');

        $values = [
            'name' => 'Bernhard',
            'extra' => 'data',
        ];

        $files = [
            'image' => [
                'error' => \UPLOAD_ERR_OK,
                'name' => 'upload.png',
                'size' => null,
                'tmp_name' => $path,
                'type' => 'image/png',
            ],
        ];

        $request = new Request([], $values, [], [], $files, [
            'REQUEST_METHOD' => $method,
        ]);

        $form = $this->getBuilder('')
            ->setMethod($method)
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('name')->getForm());
        $form->add($this->getBuilder('image')->getForm());

        $form->handleRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', \UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals($file, $form['image']->getData());
        $this->assertEquals(['extra' => 'data'], $form->getExtraData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequestWithSingleChildForm($method)
    {
        $path = tempnam(sys_get_temp_dir(), 'sf');
        touch($path);
        file_put_contents($path, 'zaza');

        $files = [
            'image' => [
                'error' => \UPLOAD_ERR_OK,
                'name' => 'upload.png',
                'size' => null,
                'tmp_name' => $path,
                'type' => 'image/png',
            ],
        ];

        $request = new Request([], [], [], [], $files, [
            'REQUEST_METHOD' => $method,
        ]);

        $form = $this->getBuilder('image', null, ['allow_file_upload' => true])
            ->setMethod($method)
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();

        $form->handleRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', \UPLOAD_ERR_OK);

        $this->assertEquals($file, $form->getData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequestWithSingleChildFormUploadedFile($method)
    {
        $path = tempnam(sys_get_temp_dir(), 'sf');
        touch($path);
        file_put_contents($path, 'zaza');

        $values = [
            'name' => 'Bernhard',
        ];

        $request = new Request([], $values, [], [], [], [
            'REQUEST_METHOD' => $method,
        ]);

        $form = $this->getBuilder('name')
            ->setMethod($method)
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();

        $form->handleRequest($request);

        $this->assertEquals('Bernhard', $form->getData());

        unlink($path);
    }

    public function testSubmitGetRequest()
    {
        $values = [
            'author' => [
                'firstName' => 'Bernhard',
                'lastName' => 'Schussek',
            ],
        ];

        $request = new Request($values, [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
        ]);

        $form = $this->getBuilder('author')
            ->setMethod('GET')
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('firstName')->getForm());
        $form->add($this->getBuilder('lastName')->getForm());

        $form->handleRequest($request);

        $this->assertEquals('Bernhard', $form['firstName']->getData());
        $this->assertEquals('Schussek', $form['lastName']->getData());
    }

    public function testSubmitGetRequestWithEmptyRootFormName()
    {
        $values = [
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
            'extra' => 'data',
        ];

        $request = new Request($values, [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
        ]);

        $form = $this->getBuilder('')
            ->setMethod('GET')
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('firstName')->getForm());
        $form->add($this->getBuilder('lastName')->getForm());

        $form->handleRequest($request);

        $this->assertEquals('Bernhard', $form['firstName']->getData());
        $this->assertEquals('Schussek', $form['lastName']->getData());
        $this->assertEquals(['extra' => 'data'], $form->getExtraData());
    }

    public function testGetErrors()
    {
        $this->form->addError($error1 = new FormError('Error 1'));
        $this->form->addError($error2 = new FormError('Error 2'));

        $errors = $this->form->getErrors();

        $this->assertSame(
            "ERROR: Error 1\n".
            "ERROR: Error 2\n",
            (string) $errors
        );

        $this->assertSame([$error1, $error2], iterator_to_array($errors));
    }

    public function testGetErrorsDeep()
    {
        $this->form->addError($error1 = new FormError('Error 1'));
        $this->form->addError($error2 = new FormError('Error 2'));

        $childForm = $this->getBuilder('Child')->getForm();
        $childForm->addError($nestedError = new FormError('Nested Error'));
        $this->form->add($childForm);

        $errors = $this->form->getErrors(true);

        $this->assertSame(
            "ERROR: Error 1\n".
            "ERROR: Error 2\n".
            "ERROR: Nested Error\n",
            (string) $errors
        );

        $this->assertSame(
            [$error1, $error2, $nestedError],
            iterator_to_array($errors)
        );
    }

    public function testGetErrorsDeepRecursive()
    {
        $this->form->addError($error1 = new FormError('Error 1'));
        $this->form->addError($error2 = new FormError('Error 2'));

        $childForm = $this->getBuilder('Child')->getForm();
        $childForm->addError($nestedError = new FormError('Nested Error'));
        $this->form->add($childForm);

        $errors = $this->form->getErrors(true, false);

        $this->assertSame(
            "ERROR: Error 1\n".
            "ERROR: Error 2\n".
            "Child:\n".
            "    ERROR: Nested Error\n",
            (string) $errors
        );

        $errorsAsArray = iterator_to_array($errors);

        $this->assertSame($error1, $errorsAsArray[0]);
        $this->assertSame($error2, $errorsAsArray[1]);
        $this->assertInstanceOf(FormErrorIterator::class, $errorsAsArray[2]);

        $nestedErrorsAsArray = iterator_to_array($errorsAsArray[2]);

        $this->assertCount(1, $nestedErrorsAsArray);
        $this->assertSame($nestedError, $nestedErrorsAsArray[0]);
    }

    public function testClearErrors()
    {
        $this->form->addError(new FormError('Error 1'));
        $this->form->addError(new FormError('Error 2'));

        $this->assertCount(2, $this->form->getErrors());

        $this->form->clearErrors();

        $this->assertCount(0, $this->form->getErrors());
    }

    public function testClearErrorsShallow()
    {
        $this->form->addError($error1 = new FormError('Error 1'));
        $this->form->addError($error2 = new FormError('Error 2'));

        $childForm = $this->getBuilder('Child')->getForm();
        $childForm->addError(new FormError('Nested Error'));
        $this->form->add($childForm);

        $this->form->clearErrors(false);

        $this->assertCount(0, $this->form->getErrors(false));
        $this->assertCount(1, $this->form->getErrors(true));
    }

    public function testClearErrorsDeep()
    {
        $this->form->addError($error1 = new FormError('Error 1'));
        $this->form->addError($error2 = new FormError('Error 2'));

        $childForm = $this->getBuilder('Child')->getForm();
        $childForm->addError($nestedError = new FormError('Nested Error'));
        $this->form->add($childForm);

        $this->form->clearErrors(true);

        $this->assertCount(0, $this->form->getErrors(false));
        $this->assertCount(0, $this->form->getErrors(true));
    }

    // Basic cases are covered in SimpleFormTest
    public function testCreateViewWithChildren()
    {
        $type = $this->createMock(ResolvedFormTypeInterface::class);
        $type1 = $this->createMock(ResolvedFormTypeInterface::class);
        $type2 = $this->createMock(ResolvedFormTypeInterface::class);
        $options = ['a' => 'Foo', 'b' => 'Bar'];
        $field1 = $this->getBuilder('foo')
            ->setType($type1)
            ->getForm();
        $field2 = $this->getBuilder('bar')
            ->setType($type2)
            ->getForm();
        $view = new FormView();
        $field1View = new FormView();
        $type1
            ->method('createView')
            ->willReturn($field1View);
        $field2View = new FormView();
        $type2
            ->method('createView')
            ->willReturn($field2View);

        $this->form = $this->getBuilder('form', null, $options)
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->setType($type)
            ->getForm();
        $this->form->add($field1);
        $this->form->add($field2);

        $assertChildViewsEqual = fn (array $childViews) => function (FormView $view) use ($childViews) {
            $this->assertSame($childViews, $view->children);
        };

        // First create the view
        $type->expects($this->once())
            ->method('createView')
            ->willReturn($view);

        // Then build it for the form itself
        $type->expects($this->once())
            ->method('buildView')
            ->with($view, $this->form, $options)
            ->willReturnCallback($assertChildViewsEqual([]));

        $this->assertSame($view, $this->form->createView());
        $this->assertSame(['foo' => $field1View, 'bar' => $field2View], $view->children);
    }

    public function testNoClickedButtonBeforeSubmission()
    {
        $this->assertNull($this->form->getClickedButton());
    }

    public function testNoClickedButton()
    {
        $parentForm = $this->getBuilder('parent')->getForm();
        $nestedForm = $this->getBuilder('nested')->getForm();

        $this->form->setParent($parentForm);
        $this->form->add($this->factory->create(SubmitType::class));
        $this->form->add($nestedForm);
        $this->form->submit([]);

        $this->assertNull($this->form->getClickedButton());
    }

    public function testClickedButton()
    {
        $button = $this->factory->create(SubmitType::class);

        $this->form->add($button);
        $this->form->submit(['submit' => '']);

        $this->assertSame($button, $this->form->getClickedButton());
    }

    public function testClickedButtonFromNestedForm()
    {
        $button = $this->factory->create(SubmitType::class);

        $nestedForm = $this->createForm('nested');
        $nestedForm->add($button);

        $this->form->add($nestedForm);
        $this->form->submit([
            'nested' => [
                'submit' => '',
            ],
        ]);

        $this->assertSame($button, $this->form->getClickedButton());
    }

    public function testClickedButtonFromParentForm()
    {
        $button = $this->factory->create(SubmitType::class);

        $parentForm = $this->createForm('');
        $parentForm->add($this->form);
        $parentForm->add($button);
        $parentForm->submit([
            'submit' => '',
        ]);

        $this->assertSame($button, $this->form->getClickedButton());
    }

    public function testDisabledButtonIsNotSubmitted()
    {
        $button = new SubmitButtonBuilder('submit');
        $submit = $button
            ->setDisabled(true)
            ->getForm();

        $form = $this->createForm()
            ->add($this->createForm('text', false))
            ->add($submit)
        ;

        $form->submit([
            'text' => '',
            'submit' => '',
        ]);

        $this->assertTrue($submit->isDisabled());
        $this->assertFalse($submit->isClicked());
        $this->assertFalse($submit->isSubmitted());
    }

    public function testArrayTransformationFailureOnSubmit()
    {
        $this->form->add($this->getBuilder('foo')->setCompound(false)->getForm());
        $this->form->add($this->getBuilder('bar', null, ['multiple' => false])->setCompound(false)->getForm());

        $this->form->submit([
            'foo' => ['foo'],
            'bar' => ['bar'],
        ]);

        $this->assertNull($this->form->get('foo')->getData());
        $this->assertSame('Submitted data was expected to be text or number, array given.', $this->form->get('foo')->getTransformationFailure()->getMessage());

        $this->assertNull($this->form->get('bar')->getData());
        $this->assertSame('Submitted data was expected to be text or number, array given.', $this->form->get('bar')->getTransformationFailure()->getMessage());
    }

    public function testFileUpload()
    {
        $reqHandler = new HttpFoundationRequestHandler();
        $this->form->add($this->getBuilder('foo')->setRequestHandler($reqHandler)->getForm());
        $this->form->add($this->getBuilder('bar')->setRequestHandler($reqHandler)->getForm());

        $this->form->submit([
            'foo' => 'Foo',
            'bar' => new UploadedFile(__FILE__, 'upload.png', 'image/png', \UPLOAD_ERR_OK),
        ]);

        $this->assertSame('Submitted data was expected to be text or number, file upload given.', $this->form->get('bar')->getTransformationFailure()->getMessage());
        $this->assertNull($this->form->get('bar')->getData());
    }

    public function testMapDateTimeObjectsWithEmptyArrayDataUsingDataMapper()
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableExceptionOnInvalidIndex()
            ->getPropertyAccessor();
        $form = $this->factory->createBuilder()
            ->setDataMapper(new DataMapper(new PropertyPathAccessor($propertyAccessor)))
            ->add('date', DateType::class, [
                'auto_initialize' => false,
                'format' => 'dd/MM/yyyy',
                'html5' => false,
                'model_timezone' => 'UTC',
                'view_timezone' => 'UTC',
                'widget' => 'single_text',
            ])
            ->getForm();

        $form->submit([
            'date' => '04/08/2022',
        ]);

        $this->assertEquals(['date' => new \DateTime('2022-08-04', new \DateTimeZone('UTC'))], $form->getData());
    }

    private function createForm(string $name = 'name', bool $compound = true): FormInterface
    {
        $builder = $this->getBuilder($name);

        if ($compound) {
            $builder
                ->setCompound(true)
                ->setDataMapper(new DataMapper())
            ;
        }

        return $builder->getForm();
    }

    private function getBuilder(string $name = 'name', string $dataClass = null, array $options = []): FormBuilder
    {
        return new FormBuilder($name, $dataClass, new EventDispatcher(), $this->factory, $options);
    }
}
