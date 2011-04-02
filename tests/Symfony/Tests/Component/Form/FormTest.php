<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__.'/Fixtures/FixedDataTransformer.php';
require_once __DIR__.'/Fixtures/FixedFilterListener.php';

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\DataTransformer\TransformationFailedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Tests\Component\Form\Fixtures\FixedDataTransformer;
use Symfony\Tests\Component\Form\Fixtures\FixedFilterListener;

class FormTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $builder;

    private $form;

    protected function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->form = $this->getBuilder()->getForm();
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConstructExpectsValidValidators()
    {
        $validators = array(new \stdClass());

        new Form('name', $this->dispatcher, array(), null, null, null, $validators);
    }

    public function testDataIsInitializedEmpty()
    {
        $norm = new FixedDataTransformer(array(
            '' => 'foo',
        ));
        $client = new FixedDataTransformer(array(
            'foo' => 'bar',
        ));

        $form = new Form('name', $this->dispatcher, array(), $client, $norm);

        $this->assertNull($form->getData());
        $this->assertSame('foo', $form->getNormData());
        $this->assertSame('bar', $form->getClientData());
    }

    public function testErrorsBubbleUpIfEnabled()
    {
        $error = new FormError('Error!');
        $parent = $this->form;
        $form = $this->getBuilder()->setErrorBubbling(true)->getForm();

        $form->setParent($parent);
        $form->addError($error);

        $this->assertEquals(array(), $form->getErrors());
        $this->assertEquals(array($error), $parent->getErrors());
    }

    public function testErrorsDontBubbleUpIfDisabled()
    {
        $error = new FormError('Error!');
        $parent = $this->form;
        $form = $this->getBuilder()->setErrorBubbling(false)->getForm();

        $form->setParent($parent);
        $form->addError($error);

        $this->assertEquals(array($error), $form->getErrors());
        $this->assertEquals(array(), $parent->getErrors());
    }

    public function testValidIfAllChildrenAreValid()
    {
        $this->form->add($this->getValidForm('firstName'));
        $this->form->add($this->getValidForm('lastName'));

        $this->form->bind(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));

        $this->assertTrue($this->form->isValid());
    }

    public function testInvalidIfChildrenIsInvalid()
    {
        $this->form->add($this->getValidForm('firstName'));
        $this->form->add($this->getInvalidForm('lastName'));

        $this->form->bind(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));

        $this->assertFalse($this->form->isValid());
    }

    public function testBind()
    {
        $child = $this->getMockForm('firstName');

        $this->form->add($child);

        $child->expects($this->once())
            ->method('bind')
            ->with($this->equalTo('Bernhard'));

        $this->form->bind(array('firstName' => 'Bernhard'));

        $this->assertEquals(array('firstName' => 'Bernhard'), $this->form->getData());
    }

    public function testBindForwardsNullIfValueIsMissing()
    {
        $child = $this->getMockForm('firstName');

        $this->form->add($child);

        $child->expects($this->once())
            ->method('bind')
            ->with($this->equalTo(null));

        $this->form->bind(array());
    }

    public function testBindIsIgnoredIfReadOnly()
    {
        $form = $this->getBuilder()
            ->setReadOnly(true)
            ->setData('initial')
            ->getForm();

        $form->bind('new');

        $this->assertEquals('initial', $form->getData());
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

    public function testAlwaysReadOnlyIfParentReadOnly()
    {
        $parent = $this->getBuilder()->setReadOnly(true)->getForm();
        $child = $this->getBuilder()->setReadOnly(false)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isReadOnly());
    }

    public function testReadOnly()
    {
        $parent = $this->getBuilder()->setReadOnly(false)->getForm();
        $child = $this->getBuilder()->setReadOnly(true)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isReadOnly());
    }

    public function testNotReadOnly()
    {
        $parent = $this->getBuilder()->setReadOnly(false)->getForm();
        $child = $this->getBuilder()->setReadOnly(false)->getForm();

        $child->setParent($parent);

        $this->assertFalse($child->isReadOnly());
    }

    public function testCloneChildren()
    {
        $child = $this->getBuilder('child')->getForm();
        $this->form->add($child);

        $clone = clone $this->form;

        $this->assertNotSame($this->form, $clone);
        $this->assertNotSame($child, $clone['child']);
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

    public function testIsEmptyIfEmptyArray()
    {
        $this->form->setData(array());

        $this->assertTrue($this->form->isEmpty());
    }

    public function testIsEmptyIfNull()
    {
        $this->form->setData(null);

        $this->assertTrue($this->form->isEmpty());
    }

    public function testIsEmptyIfEmptyString()
    {
        $this->form->setData('');

        $this->assertTrue($this->form->isEmpty());
    }

    public function testIsNotEmptyIfText()
    {
        $this->form->setData('foobar');

        $this->assertFalse($this->form->isEmpty());
    }

    public function testIsNotEmptyIfChildNotEmpty()
    {
        $child = $this->getMockForm();
        $child->expects($this->once())
            ->method('isEmpty')
            ->will($this->returnValue(false));

        $this->form->setData(null);
        $this->form->add($child);

        $this->assertFalse($this->form->isEmpty());
    }

    public function testValidIfBound()
    {
        $this->form->bind('foobar');

        $this->assertTrue($this->form->isValid());
    }

    public function testNotValidIfNotBound()
    {
        $this->assertFalse($this->form->isValid());
    }

    public function testNotValidIfErrors()
    {
        $this->form->bind('foobar');
        $this->form->addError(new FormError('Error!'));

        $this->assertFalse($this->form->isValid());
    }

    public function testNotValidIfChildNotValid()
    {
        $child = $this->getMockForm();
        $child->expects($this->once())
            ->method('isValid')
            ->will($this->returnValue(false));

        $this->form->bind('foobar');
        $this->form->add($child);

        $this->assertFalse($this->form->isValid());
    }

    public function testHasErrors()
    {
        $this->form->addError(new FormError('Error!'));

        $this->assertTrue($this->form->hasErrors());
    }

    public function testHasNoErrors()
    {
        $this->assertFalse($this->form->hasErrors());
    }

    public function testHasChildren()
    {
        $this->form->add($this->getBuilder()->getForm());

        $this->assertTrue($this->form->hasChildren());
    }

    public function testHasNoChildren()
    {
        $this->assertFalse($this->form->hasChildren());
    }

    public function testAdd()
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);

        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array('foo' => $child), $this->form->getChildren());
    }

    public function testRemove()
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);
        $this->form->remove('foo');

        $this->assertNull($child->getParent());
        $this->assertFalse($this->form->hasChildren());
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

        $this->assertEquals(2, count($this->form));
    }

    public function testIterator()
    {
        $this->form->add($this->getBuilder('foo')->getForm());
        $this->form->add($this->getBuilder('bar')->getForm());

        $this->assertSame($this->form->getChildren(), iterator_to_array($this->form));
    }

    public function testIsBound()
    {
        $this->form->bind('foobar');

        $this->assertTrue($this->form->isBound());
    }

    public function testIsNotBound()
    {
        $this->assertFalse($this->form->isBound());
    }

    public function testSetDataExecutesTransformationChain()
    {
        // use real event dispatcher now
        $form = $this->getBuilder('name', new EventDispatcher())
            ->addEventSubscriber(new FixedFilterListener(array(
                'filterSetData' => array(
                    'app' => 'filtered',
                ),
            )))
            ->setNormTransformer(new FixedDataTransformer(array(
                '' => '',
                'filtered' => 'norm',
            )))
            ->setClientTransformer(new FixedDataTransformer(array(
                '' => '',
                'norm' => 'client',
            )))
            ->getForm();

        $form->setData('app');

        $this->assertEquals('filtered', $form->getData());
        $this->assertEquals('norm', $form->getNormData());
        $this->assertEquals('client', $form->getClientData());
    }

    public function testBindExecutesTransformationChain()
    {
        // use real event dispatcher now
        $form = $this->getBuilder('name', new EventDispatcher())
            ->addEventSubscriber(new FixedFilterListener(array(
                'filterBoundClientData' => array(
                    'client' => 'filteredclient',
                ),
                'filterBoundNormData' => array(
                    'norm' => 'filterednorm',
                ),
            )))
            ->setClientTransformer(new FixedDataTransformer(array(
                '' => '',
                // direction is reversed!
                'norm' => 'filteredclient',
                'filterednorm' => 'cleanedclient'
            )))
            ->setNormTransformer(new FixedDataTransformer(array(
                '' => '',
                // direction is reversed!
                'app' => 'filterednorm',
            )))
            ->getForm();

        $form->setData('app');

        $this->assertEquals('app', $form->getData());
        $this->assertEquals('filterednorm', $form->getNormData());
        $this->assertEquals('cleanedclient', $form->getClientData());
    }

    public function testIsSynchronizedByDefault()
    {
        $this->assertTrue($this->form->isSynchronized());
    }

    public function testIsSynchronizedAfterBinding()
    {
        $this->form->bind('foobar');

        $this->assertTrue($this->form->isSynchronized());
    }

    public function testIsNotSynchronizedIfTransformationFailed()
    {
        $transformer = $this->getDataTransformer();
        $transformer->expects($this->once())
            ->method('reverseTransform')
            ->will($this->throwException(new TransformationFailedException()));

        $form = $this->getBuilder()
            ->setClientTransformer($transformer)
            ->getForm();

        $form->bind('foobar');

        $this->assertFalse($form->isSynchronized());
    }

    public function testEmptyDataCreatedBeforeTransforming()
    {
        $form = $this->getBuilder()
            ->setEmptyData('foo')
            ->setClientTransformer(new FixedDataTransformer(array(
                '' => '',
                // direction is reversed!
                'bar' => 'foo',
            )))
            ->getForm();

        $form->bind('');

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
            ->setClientTransformer(new FixedDataTransformer(array(
                '' => '',
                // direction is reversed!
                'bar' => 'foo',
            )))
            ->getForm();

        $form->bind('');

        $this->assertEquals('bar', $form->getData());
    }

    public function testAddMapsClientDataToForm()
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setDataMapper($mapper)
            ->setClientTransformer(new FixedDataTransformer(array(
                '' => '',
                'foo' => 'bar',
            )))
            ->setData('foo')
            ->getForm();

        $child = $this->getBuilder()->getForm();
        $mapper->expects($this->once())
            ->method('mapDataToForm')
            ->with('bar', $child);

        $form->add($child);
    }

    public function testSetDataMapsClientDataToChildren()
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setDataMapper($mapper)
            ->setClientTransformer(new FixedDataTransformer(array(
                '' => '',
                'foo' => 'bar',
            )))
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->getForm());

        $mapper->expects($this->once())
            ->method('mapDataToForms')
            ->with('bar', array('firstName' => $child1, 'lastName' => $child2));

        $form->setData('foo');
    }

    public function testBindMapsBoundChildrenOntoExistingClientData()
    {
        $test = $this;
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setDataMapper($mapper)
            ->setClientTransformer(new FixedDataTransformer(array(
                '' => '',
                'foo' => 'bar',
            )))
            ->setData('foo')
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->getForm());

        $mapper->expects($this->once())
            ->method('mapFormsToData')
            ->with(array('firstName' => $child1, 'lastName' => $child2), 'bar')
            ->will($this->returnCallback(function ($children, $bar) use ($test) {
                $test->assertEquals('Bernhard', $children['firstName']->getData());
                $test->assertEquals('Schussek', $children['lastName']->getData());
            }));

        $form->bind(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));
    }

    public function testBindMapsBoundChildrenOntoEmptyData()
    {
        $test = $this;
        $mapper = $this->getDataMapper();
        $object = new \stdClass();
        $form = $this->getBuilder()
            ->setDataMapper($mapper)
            ->setEmptyData($object)
            ->setData(null)
            ->getForm();

        $form->add($child = $this->getBuilder('name')->getForm());

        $mapper->expects($this->once())
            ->method('mapFormsToData')
            ->with(array('name' => $child), $object);

        $form->bind(array(
            'name' => 'Bernhard',
        ));
    }

    public function testBindValidatesAfterTransformation()
    {
        $test = $this;
        $validator = $this->getFormValidator();
        $form = $this->getBuilder()
            ->addValidator($validator)
            ->getForm();

        $validator->expects($this->once())
            ->method('validate')
            ->with($form)
            ->will($this->returnCallback(function ($form) use ($test) {
                $test->assertEquals('foobar', $form->getData());
            }));

        $form->bind('foobar');
    }

    public function requestMethodProvider()
    {
        return array(
            array('POST'),
            array('PUT'),
        );
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testBindPostOrPutRequest($method)
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

        $form = $this->getBuilder('author')->getForm();
        $form->add($this->getBuilder('name')->getForm());
        $form->add($this->getBuilder('image')->getForm());

        $form->bindRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', 123, UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals($file, $form['image']->getData());

        unlink($path);
    }

    public function testBindGetRequest()
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

        $form = $this->getBuilder('author')->getForm();
        $form->add($this->getBuilder('firstName')->getForm());
        $form->add($this->getBuilder('lastName')->getForm());

        $form->bindRequest($request);

        $this->assertEquals('Bernhard', $form['firstName']->getData());
        $this->assertEquals('Schussek', $form['lastName']->getData());
    }

    protected function getBuilder($name = 'name', EventDispatcherInterface $dispatcher = null)
    {
        return new FormBuilder($name, $dispatcher ?: $this->dispatcher);
    }

    protected function getMockForm($name = 'name')
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');

        $form->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));

        return $form;
    }

    protected function getValidForm($name)
    {
        $form = $this->getMockForm($name);

        $form->expects($this->any())
            ->method('isValid')
            ->will($this->returnValue(true));

        return $form;
    }

    protected function getInvalidForm($name)
    {
        $form = $this->getMockForm($name);

        $form->expects($this->any())
            ->method('isValid')
            ->will($this->returnValue(false));

        return $form;
    }

    protected function getDataMapper()
    {
        return $this->getMock('Symfony\Component\Form\DataMapper\DataMapperInterface');
    }

    protected function getDataTransformer()
    {
        return $this->getMock('Symfony\Component\Form\DataTransformer\DataTransformerInterface');
    }

    protected function getFormValidator()
    {
        return $this->getMock('Symfony\Component\Form\Validator\FormValidatorInterface');
    }
}