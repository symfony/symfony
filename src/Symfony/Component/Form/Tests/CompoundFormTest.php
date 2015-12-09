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

use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\SubmitButtonBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;

class CompoundFormTest extends AbstractFormTest
{
    public function testValidIfAllChildrenAreValid()
    {
        $this->form->add($this->getBuilder('firstName')->getForm());
        $this->form->add($this->getBuilder('lastName')->getForm());

        $this->form->submit(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));

        $this->assertTrue($this->form->isValid());
    }

    public function testInvalidIfChildIsInvalid()
    {
        $this->form->add($this->getBuilder('firstName')->getForm());
        $this->form->add($this->getBuilder('lastName')->getForm());

        $this->form->submit(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));

        $this->form->get('lastName')->addError(new FormError('Invalid'));

        $this->assertFalse($this->form->isValid());
    }

    public function testDisabledFormsValidEvenIfChildrenInvalid()
    {
        $form = $this->getBuilder('person')
            ->setDisabled(true)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->add($this->getBuilder('name'))
            ->getForm();

        $form->submit(array('name' => 'Jacques Doe'));

        $form->get('name')->addError(new FormError('Invalid'));

        $this->assertTrue($form->isValid());
    }

    public function testSubmitForwardsNullIfNotClearMissingButValueIsExplicitlyNull()
    {
        $child = $this->getMockForm('firstName');

        $this->form->add($child);

        $child->expects($this->once())
            ->method('submit')
            ->with($this->equalTo(null));

        $this->form->submit(array('firstName' => null), false);
    }

    public function testSubmitForwardsNullIfValueIsMissing()
    {
        $child = $this->getMockForm('firstName');

        $this->form->add($child);

        $child->expects($this->once())
            ->method('submit')
            ->with($this->equalTo(null));

        $this->form->submit(array());
    }

    public function testSubmitDoesNotForwardNullIfNotClearMissing()
    {
        $child = $this->getMockForm('firstName');

        $this->form->add($child);

        $child->expects($this->never())
            ->method('submit');

        $this->form->submit(array(), false);
    }

    public function testSubmitDoesNotAddExtraFieldForNullValues()
    {
        $factory = Forms::createFormFactoryBuilder()
            ->getFormFactory();

        $child = $factory->createNamed('file', 'Symfony\Component\Form\Extension\Core\Type\FileType', null, array('auto_initialize' => false));

        $this->form->add($child);
        $this->form->submit(array('file' => null), false);

        $this->assertCount(0, $this->form->getExtraData());
    }

    public function testClearMissingFlagIsForwarded()
    {
        $child = $this->getMockForm('firstName');

        $this->form->add($child);

        $child->expects($this->once())
            ->method('submit')
            ->with($this->equalTo('foo'), false);

        $this->form->submit(array('firstName' => 'foo'), false);
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
        $child = $this->getMockForm();
        $child->expects($this->once())
            ->method('isEmpty')
            ->will($this->returnValue(false));

        $this->form->setData(null);
        $this->form->add($child);

        $this->assertFalse($this->form->isEmpty());
    }

    public function testAdd()
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array('foo' => $child), $this->form->all());
    }

    public function testAddUsingNameAndType()
    {
        $child = $this->getBuilder('foo')->getForm();

        $this->factory->expects($this->once())
            ->method('createNamed')
            ->with('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array(
                'bar' => 'baz',
                'auto_initialize' => false,
            ))
            ->will($this->returnValue($child));

        $this->form->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType', array('bar' => 'baz'));

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array('foo' => $child), $this->form->all());
    }

    public function testAddUsingIntegerNameAndType()
    {
        $child = $this->getBuilder(0)->getForm();

        $this->factory->expects($this->once())
            ->method('createNamed')
            ->with('0', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array(
                'bar' => 'baz',
                'auto_initialize' => false,
            ))
            ->will($this->returnValue($child));

        // in order to make casting unnecessary
        $this->form->add(0, 'Symfony\Component\Form\Extension\Core\Type\TextType', array('bar' => 'baz'));

        $this->assertTrue($this->form->has(0));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array(0 => $child), $this->form->all());
    }

    public function testAddWithoutType()
    {
        $child = $this->getBuilder('foo')->getForm();

        $this->factory->expects($this->once())
            ->method('createNamed')
            ->with('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->will($this->returnValue($child));

        $this->form->add('foo');

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array('foo' => $child), $this->form->all());
    }

    public function testAddUsingNameButNoType()
    {
        $this->form = $this->getBuilder('name', null, '\stdClass')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();

        $child = $this->getBuilder('foo')->getForm();

        $this->factory->expects($this->once())
            ->method('createForProperty')
            ->with('\stdClass', 'foo')
            ->will($this->returnValue($child));

        $this->form->add('foo');

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array('foo' => $child), $this->form->all());
    }

    public function testAddUsingNameButNoTypeAndOptions()
    {
        $this->form = $this->getBuilder('name', null, '\stdClass')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();

        $child = $this->getBuilder('foo')->getForm();

        $this->factory->expects($this->once())
            ->method('createForProperty')
            ->with('\stdClass', 'foo', null, array(
                'bar' => 'baz',
                'auto_initialize' => false,
            ))
            ->will($this->returnValue($child));

        $this->form->add('foo', null, array('bar' => 'baz'));

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array('foo' => $child), $this->form->all());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\AlreadySubmittedException
     */
    public function testAddThrowsExceptionIfAlreadySubmitted()
    {
        $this->form->submit(array());
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

    /**
     * @expectedException \Symfony\Component\Form\Exception\AlreadySubmittedException
     */
    public function testRemoveThrowsExceptionIfAlreadySubmitted()
    {
        $this->form->add($this->getBuilder('foo')->setCompound(false)->getForm());
        $this->form->submit(array('foo' => 'bar'));
        $this->form->remove('foo');
    }

    public function testRemoveIgnoresUnknownName()
    {
        $this->form->remove('notexisting');
    }

    public function testArrayAccess()
    {
        $child = $this->getBuilder('foo')->getForm();

        $this->form[] = $child;

        $this->assertTrue(isset($this->form['foo']));
        $this->assertSame($child, $this->form['foo']);

        unset($this->form['foo']);

        $this->assertFalse(isset($this->form['foo']));
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

    public function testAddMapsViewDataToFormIfInitialized()
    {
        $test = $this;
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'foo' => 'bar',
            )))
            ->setData('foo')
            ->getForm();

        $child = $this->getBuilder()->getForm();
        $mapper->expects($this->once())
            ->method('mapDataToForms')
            ->with('bar', $this->isInstanceOf('\RecursiveIteratorIterator'))
            ->will($this->returnCallback(function ($data, \RecursiveIteratorIterator $iterator) use ($child, $test) {
                $test->assertInstanceOf('Symfony\Component\Form\Util\InheritDataAwareIterator', $iterator->getInnerIterator());
                $test->assertSame(array($child->getName() => $child), iterator_to_array($iterator));
            }));

        $form->initialize();
        $form->add($child);
    }

    public function testAddDoesNotMapViewDataToFormIfNotInitialized()
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->getForm();

        $child = $this->getBuilder()->getForm();
        $mapper->expects($this->never())
            ->method('mapDataToForms');

        $form->add($child);
    }

    public function testAddDoesNotMapViewDataToFormIfInheritData()
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->setInheritData(true)
            ->getForm();

        $child = $this->getBuilder()->getForm();
        $mapper->expects($this->never())
            ->method('mapDataToForms');

        $form->initialize();
        $form->add($child);
    }

    public function testSetDataSupportsDynamicAdditionAndRemovalOfChildren()
    {
        $form = $this->getBuilder()
            ->setCompound(true)
            // We test using PropertyPathMapper on purpose. The traversal logic
            // is currently contained in InheritDataAwareIterator, but even
            // if that changes, this test should still function.
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();

        $child = $this->getMockForm('child');
        $childToBeRemoved = $this->getMockForm('removed');
        $childToBeAdded = $this->getMockForm('added');

        $form->add($child);
        $form->add($childToBeRemoved);

        $child->expects($this->once())
            ->method('setData')
            ->will($this->returnCallback(function () use ($form, $childToBeAdded) {
                $form->remove('removed');
                $form->add($childToBeAdded);
            }));

        $childToBeRemoved->expects($this->never())
            ->method('setData');

        // once when it it is created, once when it is added
        $childToBeAdded->expects($this->exactly(2))
            ->method('setData');

        // pass NULL to all children
        $form->setData(array());
    }

    public function testSetDataMapsViewDataToChildren()
    {
        $test = $this;
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'foo' => 'bar',
            )))
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->getForm());

        $mapper->expects($this->once())
            ->method('mapDataToForms')
            ->with('bar', $this->isInstanceOf('\RecursiveIteratorIterator'))
            ->will($this->returnCallback(function ($data, \RecursiveIteratorIterator $iterator) use ($child1, $child2, $test) {
                $test->assertInstanceOf('Symfony\Component\Form\Util\InheritDataAwareIterator', $iterator->getInnerIterator());
                $test->assertSame(array('firstName' => $child1, 'lastName' => $child2), iterator_to_array($iterator));
            }));

        $form->setData('foo');
    }

    public function testSubmitSupportsDynamicAdditionAndRemovalOfChildren()
    {
        $child = $this->getMockForm('child');
        $childToBeRemoved = $this->getMockForm('removed');
        $childToBeAdded = $this->getMockForm('added');

        $this->form->add($child);
        $this->form->add($childToBeRemoved);

        $form = $this->form;

        $child->expects($this->once())
            ->method('submit')
            ->will($this->returnCallback(function () use ($form, $childToBeAdded) {
                $form->remove('removed');
                $form->add($childToBeAdded);
            }));

        $childToBeRemoved->expects($this->never())
            ->method('submit');

        $childToBeAdded->expects($this->once())
            ->method('submit');

        // pass NULL to all children
        $this->form->submit(array());
    }

    public function testSubmitMapsSubmittedChildrenOntoExistingViewData()
    {
        $test = $this;
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'foo' => 'bar',
            )))
            ->setData('foo')
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->setCompound(false)->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->setCompound(false)->getForm());

        $mapper->expects($this->once())
            ->method('mapFormsToData')
            ->with($this->isInstanceOf('\RecursiveIteratorIterator'), 'bar')
            ->will($this->returnCallback(function (\RecursiveIteratorIterator $iterator) use ($child1, $child2, $test) {
                $test->assertInstanceOf('Symfony\Component\Form\Util\InheritDataAwareIterator', $iterator->getInnerIterator());
                $test->assertSame(array('firstName' => $child1, 'lastName' => $child2), iterator_to_array($iterator));
                $test->assertEquals('Bernhard', $child1->getData());
                $test->assertEquals('Schussek', $child2->getData());
            }));

        $form->submit(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));
    }

    public function testMapFormsToDataIsNotInvokedIfInheritData()
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->setInheritData(true)
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'foo' => 'bar',
            )))
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->setCompound(false)->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->setCompound(false)->getForm());

        $mapper->expects($this->never())
            ->method('mapFormsToData');

        $form->submit(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));
    }

    /*
     * https://github.com/symfony/symfony/issues/4480
     */
    public function testSubmitRestoresViewDataIfCompoundAndEmpty()
    {
        $mapper = $this->getDataMapper();
        $object = new \stdClass();
        $form = $this->getBuilder('name', null, 'stdClass')
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->setData($object)
            ->getForm();

        $form->submit(array());

        $this->assertSame($object, $form->getData());
    }

    public function testSubmitMapsSubmittedChildrenOntoEmptyData()
    {
        $test = $this;
        $mapper = $this->getDataMapper();
        $object = new \stdClass();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->setEmptyData($object)
            ->setData(null)
            ->getForm();

        $form->add($child = $this->getBuilder('name')->setCompound(false)->getForm());

        $mapper->expects($this->once())
            ->method('mapFormsToData')
            ->with($this->isInstanceOf('\RecursiveIteratorIterator'), $object)
            ->will($this->returnCallback(function (\RecursiveIteratorIterator $iterator) use ($child, $test) {
                $test->assertInstanceOf('Symfony\Component\Form\Util\InheritDataAwareIterator', $iterator->getInnerIterator());
                $test->assertSame(array('name' => $child), iterator_to_array($iterator));
            }));

        $form->submit(array(
            'name' => 'Bernhard',
        ));
    }

    public function requestMethodProvider()
    {
        return array(
            array('POST'),
            array('PUT'),
            array('DELETE'),
            array('PATCH'),
        );
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequest($method)
    {
        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

        $values = array(
            'author' => array(
                'name' => 'Bernhard',
                'image' => array('filename' => 'foobar.png'),
            ),
        );

        $files = array(
            'author' => array(
                'error' => array('image' => UPLOAD_ERR_OK),
                'name' => array('image' => 'upload.png'),
                'size' => array('image' => 123),
                'tmp_name' => array('image' => $path),
                'type' => array('image' => 'image/png'),
            ),
        );

        $request = new Request(array(), $values, array(), array(), $files, array(
            'REQUEST_METHOD' => $method,
        ));

        $form = $this->getBuilder('author')
            ->setMethod($method)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('name')->getForm());
        $form->add($this->getBuilder('image')->getForm());

        $form->handleRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', 123, UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals($file, $form['image']->getData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequestWithEmptyRootFormName($method)
    {
        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

        $values = array(
            'name' => 'Bernhard',
            'extra' => 'data',
        );

        $files = array(
            'image' => array(
                'error' => UPLOAD_ERR_OK,
                'name' => 'upload.png',
                'size' => 123,
                'tmp_name' => $path,
                'type' => 'image/png',
            ),
        );

        $request = new Request(array(), $values, array(), array(), $files, array(
            'REQUEST_METHOD' => $method,
        ));

        $form = $this->getBuilder('')
            ->setMethod($method)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('name')->getForm());
        $form->add($this->getBuilder('image')->getForm());

        $form->handleRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', 123, UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals($file, $form['image']->getData());
        $this->assertEquals(array('extra' => 'data'), $form->getExtraData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequestWithSingleChildForm($method)
    {
        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

        $files = array(
            'image' => array(
                'error' => UPLOAD_ERR_OK,
                'name' => 'upload.png',
                'size' => 123,
                'tmp_name' => $path,
                'type' => 'image/png',
            ),
        );

        $request = new Request(array(), array(), array(), array(), $files, array(
            'REQUEST_METHOD' => $method,
        ));

        $form = $this->getBuilder('image')
            ->setMethod($method)
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();

        $form->handleRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', 123, UPLOAD_ERR_OK);

        $this->assertEquals($file, $form->getData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequestWithSingleChildFormUploadedFile($method)
    {
        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

        $values = array(
            'name' => 'Bernhard',
        );

        $request = new Request(array(), $values, array(), array(), array(), array(
            'REQUEST_METHOD' => $method,
        ));

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
        $values = array(
            'author' => array(
                'firstName' => 'Bernhard',
                'lastName' => 'Schussek',
            ),
        );

        $request = new Request($values, array(), array(), array(), array(), array(
            'REQUEST_METHOD' => 'GET',
        ));

        $form = $this->getBuilder('author')
            ->setMethod('GET')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
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
        $values = array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
            'extra' => 'data',
        );

        $request = new Request($values, array(), array(), array(), array(), array(
            'REQUEST_METHOD' => 'GET',
        ));

        $form = $this->getBuilder('')
            ->setMethod('GET')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('firstName')->getForm());
        $form->add($this->getBuilder('lastName')->getForm());

        $form->handleRequest($request);

        $this->assertEquals('Bernhard', $form['firstName']->getData());
        $this->assertEquals('Schussek', $form['lastName']->getData());
        $this->assertEquals(array('extra' => 'data'), $form->getExtraData());
    }

    /**
     * @group legacy
     */
    public function testGetErrorsAsStringDeep()
    {
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();

        $this->form->addError(new FormError('Error!'));

        $parent->add($this->form);
        $parent->add($this->getBuilder('foo')->getForm());

        $this->assertSame(
             "name:\n".
             "    ERROR: Error!\n",
             $parent->getErrorsAsString()
        );
    }

    /**
     * @group legacy
     */
    public function testGetErrorsAsStringDeepWithIndentation()
    {
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();

        $this->form->addError(new FormError('Error!'));

        $parent->add($this->form);
        $parent->add($this->getBuilder('foo')->getForm());

        $this->assertSame(
             "    name:\n".
             "        ERROR: Error!\n",
             $parent->getErrorsAsString(4)
        );
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

        $this->assertSame(array($error1, $error2), iterator_to_array($errors));
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
             array($error1, $error2, $nestedError),
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
        $this->assertInstanceOf('Symfony\Component\Form\FormErrorIterator', $errorsAsArray[2]);

        $nestedErrorsAsArray = iterator_to_array($errorsAsArray[2]);

        $this->assertCount(1, $nestedErrorsAsArray);
        $this->assertSame($nestedError, $nestedErrorsAsArray[0]);
    }

    // Basic cases are covered in SimpleFormTest
    public function testCreateViewWithChildren()
    {
        $type = $this->getMock('Symfony\Component\Form\ResolvedFormTypeInterface');
        $options = array('a' => 'Foo', 'b' => 'Bar');
        $field1 = $this->getMockForm('foo');
        $field2 = $this->getMockForm('bar');
        $view = new FormView();
        $field1View = new FormView();
        $field2View = new FormView();

        $this->form = $this->getBuilder('form', null, null, $options)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setType($type)
            ->getForm();
        $this->form->add($field1);
        $this->form->add($field2);

        $test = $this;

        $assertChildViewsEqual = function (array $childViews) use ($test) {
            return function (FormView $view) use ($test, $childViews) {
                /* @var \PHPUnit_Framework_TestCase $test */
                $test->assertSame($childViews, $view->children);
            };
        };

        // First create the view
        $type->expects($this->once())
            ->method('createView')
            ->will($this->returnValue($view));

        // Then build it for the form itself
        $type->expects($this->once())
            ->method('buildView')
            ->with($view, $this->form, $options)
            ->will($this->returnCallback($assertChildViewsEqual(array())));

        // Then add the first child form
        $field1->expects($this->once())
            ->method('createView')
            ->will($this->returnValue($field1View));

        // Then the second child form
        $field2->expects($this->once())
            ->method('createView')
            ->will($this->returnValue($field2View));

        // Again build the view for the form itself. This time the child views
        // exist.
        $type->expects($this->once())
            ->method('finishView')
            ->with($view, $this->form, $options)
            ->will($this->returnCallback($assertChildViewsEqual(array('foo' => $field1View, 'bar' => $field2View))));

        $this->assertSame($view, $this->form->createView());
    }

    public function testNoClickedButtonBeforeSubmission()
    {
        $this->assertNull($this->form->getClickedButton());
    }

    public function testNoClickedButton()
    {
        $button = $this->getMockBuilder('Symfony\Component\Form\SubmitButton')
            ->setConstructorArgs(array(new SubmitButtonBuilder('submit')))
            ->setMethods(array('isClicked'))
            ->getMock();

        $button->expects($this->any())
            ->method('isClicked')
            ->will($this->returnValue(false));

        $parentForm = $this->getBuilder('parent')->getForm();
        $nestedForm = $this->getBuilder('nested')->getForm();

        $this->form->setParent($parentForm);
        $this->form->add($button);
        $this->form->add($nestedForm);
        $this->form->submit(array());

        $this->assertNull($this->form->getClickedButton());
    }

    public function testClickedButton()
    {
        $button = $this->getMockBuilder('Symfony\Component\Form\SubmitButton')
            ->setConstructorArgs(array(new SubmitButtonBuilder('submit')))
            ->setMethods(array('isClicked'))
            ->getMock();

        $button->expects($this->any())
            ->method('isClicked')
            ->will($this->returnValue(true));

        $this->form->add($button);
        $this->form->submit(array());

        $this->assertSame($button, $this->form->getClickedButton());
    }

    public function testClickedButtonFromNestedForm()
    {
        $button = $this->getBuilder('submit')->getForm();

        $nestedForm = $this->getMockBuilder('Symfony\Component\Form\Form')
            ->setConstructorArgs(array($this->getBuilder('nested')))
            ->setMethods(array('getClickedButton'))
            ->getMock();

        $nestedForm->expects($this->any())
            ->method('getClickedButton')
            ->will($this->returnValue($button));

        $this->form->add($nestedForm);
        $this->form->submit(array());

        $this->assertSame($button, $this->form->getClickedButton());
    }

    public function testClickedButtonFromParentForm()
    {
        $button = $this->getBuilder('submit')->getForm();

        $parentForm = $this->getMockBuilder('Symfony\Component\Form\Form')
            ->setConstructorArgs(array($this->getBuilder('parent')))
            ->setMethods(array('getClickedButton'))
            ->getMock();

        $parentForm->expects($this->any())
            ->method('getClickedButton')
            ->will($this->returnValue($button));

        $this->form->setParent($parentForm);
        $this->form->submit(array());

        $this->assertSame($button, $this->form->getClickedButton());
    }

    protected function createForm()
    {
        return $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
    }
}
