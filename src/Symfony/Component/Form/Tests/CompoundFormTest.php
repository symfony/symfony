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

    public function testValidIfChildIsNotSubmitted()
    {
        $this->form->add($this->getBuilder('firstName')->getForm());
        $this->form->add($this->getBuilder('lastName')->getForm());

        $this->form->submit(array(
            'firstName' => 'Bernhard',
        ));

        // "lastName" is not "valid" because it was not submitted. This happens
        // for example in PATCH requests. The parent form should still be
        // considered valid.

        $this->assertTrue($this->form->isValid());
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

        $child = $factory->create('file', null, array('auto_initialize' => false));

        $this->form->add($child);
        $this->form->submit(array('file' => null));

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
            ->with('foo', 'text', null, array(
                'bar' => 'baz',
                'auto_initialize' => false,
            ))
            ->will($this->returnValue($child));

        $this->form->add('foo', 'text', array('bar' => 'baz'));

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array('foo' => $child), $this->form->all());
    }

    public function testAddUsingIntegerNameAndType()
    {
        $child = $this->getBuilder(0)->getForm();

        $this->factory->expects($this->once())
            ->method('createNamed')
            ->with('0', 'text', null, array(
                'bar' => 'baz',
                'auto_initialize' => false,
            ))
            ->will($this->returnValue($child));

        // in order to make casting unnecessary
        $this->form->add(0, 'text', array('bar' => 'baz'));

        $this->assertTrue($this->form->has(0));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array(0 => $child), $this->form->all());
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
                $test->assertSame(array($child), iterator_to_array($iterator));
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
            'extra' => 'data'
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

    public function testGetErrorsAsStringDeep()
    {
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();

        $this->form->addError(new FormError('Error!'));

        $parent->add($this->form);
        $parent->add($this->getBuilder('foo')->getForm());

        $this->assertEquals("name:\n    ERROR: Error!\nfoo:\n    No errors\n", $parent->getErrorsAsString());
    }

    protected function createForm()
    {
        return $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
    }
}
