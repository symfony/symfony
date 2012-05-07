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

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;
use Symfony\Component\Form\Tests\Fixtures\FixedFilterListener;

class FormTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $factory;

    private $builder;

    private $form;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->form = $this->getBuilder()->getForm();
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->factory = null;
        $this->form = null;
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConstructExpectsValidValidators()
    {
        $validators = array(new \stdClass());

        new Form('name', $this->dispatcher, array(), array(), array(), null, $validators);
    }

    public function getHtml4Ids()
    {
        return array(
            array('a0', true),
            array('a9', true),
            array('z0', true),
            array('A0', true),
            array('A9', true),
            array('Z0', true),
            array('#', false),
            array('a#', false),
            array('a$', false),
            array('a%', false),
            array('a ', false),
            array("a\t", false),
            array("a\n", false),
            array('a-', true),
            array('a_', true),
            array('a:', true),
            // Periods are allowed by the HTML4 spec, but disallowed by us
            // because they break the generated property paths
            array('a.', false),
            // Contrary to the HTML4 spec, we allow names starting with a
            // number, otherwise naming fields by collection indices is not
            // possible.
            // For root forms, leading digits will be stripped from the
            // "id" attribute to produce valid HTML4.
            array('0', true),
            array('9', true),
            // Contrary to the HTML4 spec, we allow names starting with an
            // underscore, since this is already a widely used practice in
            // Symfony2.
            // For root forms, leading underscores will be stripped from the
            // "id" attribute to produce valid HTML4.
            array('_', true),
        );
    }

    /**
     * @dataProvider getHtml4Ids
     */
    public function testConstructAcceptsOnlyNamesValidAsIdsInHtml4($name, $accepted)
    {
        try {
            new Form($name, $this->dispatcher);
            if (!$accepted) {
                $this->fail(sprintf('The value "%s" should not be accepted', $name));
            }
        } catch (\InvalidArgumentException $e) {
            // if the value was not accepted, but should be, rethrow exception
            if ($accepted) {
                throw $e;
            }
        }
    }

    public function testDataIsInitializedEmpty()
    {
        $norm = new FixedDataTransformer(array(
            '' => 'foo',
        ));
        $client = new FixedDataTransformer(array(
            'foo' => 'bar',
        ));

        $form = new Form('name', $this->dispatcher, array(), array($client), array($norm));

        $this->assertNull($form->getData());
        $this->assertSame('foo', $form->getNormData());
        $this->assertSame('bar', $form->getClientData());
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

    /**
     * @expectedException Symfony\Component\Form\Exception\AlreadyBoundException
     */
    public function testBindThrowsExceptionIfAlreadyBound()
    {
        $this->form->bind(array());
        $this->form->bind(array());
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

    public function testBindIsIgnoredIfDisabled()
    {
        $form = $this->getBuilder()
            ->setDisabled(true)
            ->setData('initial')
            ->getForm();

        $form->bind('new');

        $this->assertEquals('initial', $form->getData());
        $this->assertTrue($form->isBound());
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

    public function testAlwaysDisabledIfParentDisabled()
    {
        $parent = $this->getBuilder()->setDisabled(true)->getForm();
        $child = $this->getBuilder()->setDisabled(false)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isDisabled());
    }

    public function testDisabled()
    {
        $parent = $this->getBuilder()->setDisabled(false)->getForm();
        $child = $this->getBuilder()->setDisabled(true)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isDisabled());
    }

    public function testNotDisabled()
    {
        $parent = $this->getBuilder()->setDisabled(false)->getForm();
        $child = $this->getBuilder()->setDisabled(false)->getForm();

        $child->setParent($parent);

        $this->assertFalse($child->isDisabled());
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

    public function testEmptyIfEmptyArray()
    {
        $this->form->setData(array());

        $this->assertTrue($this->form->isEmpty());
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

    public function testValidIfBound()
    {
        $this->form->bind('foobar');

        $this->assertTrue($this->form->isValid());
    }

    public function testValidIfBoundAndDisabled()
    {
        $form = $this->getBuilder()->setDisabled(true)->getForm();
        $form->bind('foobar');

        $this->assertTrue($form->isValid());
    }

    public function testValidIfBoundAndDisabledWithChildren()
    {
        $this->factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('text', 'name', null, array())
            ->will($this->returnValue($this->getBuilder('name')));

        $form = $this->getBuilder('person')
            ->setDisabled(true)
            ->add('name', 'text')
            ->getForm();
        $form->bind(array('name' => 'Jacques Doe'));

        $this->assertTrue($form->isValid());
    }

    /**
     * @expectedException \LogicException
     */
    public function testNotValidIfNotBound()
    {
        $this->form->isValid();
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

        $this->form->add($child);
        $this->form->bind(array());

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

    /**
     * @expectedException Symfony\Component\Form\Exception\AlreadyBoundException
     */
    public function testSetParentThrowsExceptionIfAlreadyBound()
    {
        $this->form->bind(array());
        $this->form->setParent($this->getBuilder('parent')->getForm());
    }

    public function testAdd()
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);

        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array('foo' => $child), $this->form->getChildren());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\AlreadyBoundException
     */
    public function testAddThrowsExceptionIfAlreadyBound()
    {
        $this->form->bind(array());
        $this->form->add($this->getBuilder('foo')->getForm());
    }

    public function testRemove()
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);
        $this->form->remove('foo');

        $this->assertNull($child->getParent());
        $this->assertFalse($this->form->hasChildren());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\AlreadyBoundException
     */
    public function testRemoveThrowsExceptionIfAlreadyBound()
    {
        $this->form->add($this->getBuilder('foo')->getForm());
        $this->form->bind(array('foo' => 'bar'));
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

        $this->assertSame($this->form->getChildren(), iterator_to_array($this->form));
    }

    public function testBound()
    {
        $this->form->bind('foobar');

        $this->assertTrue($this->form->isBound());
    }

    public function testNotBound()
    {
        $this->assertFalse($this->form->isBound());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\AlreadyBoundException
     */
    public function testSetDataThrowsExceptionIfAlreadyBound()
    {
        $this->form->bind(array());
        $this->form->setData(null);
    }

    public function testSetDataExecutesTransformationChain()
    {
        // use real event dispatcher now
        $form = $this->getBuilder('name', new EventDispatcher())
            ->addEventSubscriber(new FixedFilterListener(array(
                'onSetData' => array(
                    'app' => 'filtered',
                ),
            )))
            ->appendNormTransformer(new FixedDataTransformer(array(
                '' => '',
                'filtered' => 'norm',
            )))
            ->appendClientTransformer(new FixedDataTransformer(array(
                '' => '',
                'norm' => 'client',
            )))
            ->getForm();

        $form->setData('app');

        $this->assertEquals('filtered', $form->getData());
        $this->assertEquals('norm', $form->getNormData());
        $this->assertEquals('client', $form->getClientData());
    }

    public function testSetDataExecutesClientTransformersInOrder()
    {
        $form = $this->getBuilder()
            ->appendClientTransformer(new FixedDataTransformer(array(
                '' => '',
                'first' => 'second',
            )))
            ->appendClientTransformer(new FixedDataTransformer(array(
                '' => '',
                'second' => 'third',
            )))
            ->getForm();

        $form->setData('first');

        $this->assertEquals('third', $form->getClientData());
    }

    public function testSetDataExecutesNormTransformersInOrder()
    {
        $form = $this->getBuilder()
            ->appendNormTransformer(new FixedDataTransformer(array(
                '' => '',
                'first' => 'second',
            )))
            ->appendNormTransformer(new FixedDataTransformer(array(
                '' => '',
                'second' => 'third',
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
        $this->assertSame('1', $form->getClientData());
    }

    /*
     * Data in client format should, if possible, always be a string to
     * facilitate differentiation between '0' and ''
     */
    public function testSetDataConvertsScalarToStringIfOnlyNormTransformer()
    {
        $form = $this->getBuilder()
            ->appendNormTransformer(new FixedDataTransformer(array(
                '' => '',
                1 => 23,
            )))
            ->getForm();

        $form->setData(1);

        $this->assertSame(1, $form->getData());
        $this->assertSame(23, $form->getNormData());
        $this->assertSame('23', $form->getClientData());
    }

    /*
     * NULL remains NULL in app and norm format to remove the need to treat
     * empty values and NULL explicitely in the application
     */
    public function testSetDataConvertsNullToStringIfNoTransformer()
    {
        $form = $this->getBuilder()->getForm();

        $form->setData(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getClientData());
    }

    public function testBindConvertsEmptyToNullIfNoTransformer()
    {
        $form = $this->getBuilder()->getForm();

        $form->bind('');

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getClientData());
    }

    public function testBindExecutesTransformationChain()
    {
        // use real event dispatcher now
        $form = $this->getBuilder('name', new EventDispatcher())
            ->addEventSubscriber(new FixedFilterListener(array(
                'onBindClientData' => array(
                    'client' => 'filteredclient',
                ),
                'onBindNormData' => array(
                    'norm' => 'filterednorm',
                ),
            )))
            ->appendClientTransformer(new FixedDataTransformer(array(
                '' => '',
                // direction is reversed!
                'norm' => 'filteredclient',
                'filterednorm' => 'cleanedclient'
            )))
            ->appendNormTransformer(new FixedDataTransformer(array(
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

    public function testBindExecutesClientTransformersInReverseOrder()
    {
        $form = $this->getBuilder()
            ->appendClientTransformer(new FixedDataTransformer(array(
                '' => '',
                'third' => 'second',
            )))
            ->appendClientTransformer(new FixedDataTransformer(array(
                '' => '',
                'second' => 'first',
            )))
            ->getForm();

        $form->bind('first');

        $this->assertEquals('third', $form->getNormData());
    }

    public function testBindExecutesNormTransformersInReverseOrder()
    {
        $form = $this->getBuilder()
            ->appendNormTransformer(new FixedDataTransformer(array(
                '' => '',
                'third' => 'second',
            )))
            ->appendNormTransformer(new FixedDataTransformer(array(
                '' => '',
                'second' => 'first',
            )))
            ->getForm();

        $form->bind('first');

        $this->assertEquals('third', $form->getData());
    }

    public function testSynchronizedByDefault()
    {
        $this->assertTrue($this->form->isSynchronized());
    }

    public function testSynchronizedAfterBinding()
    {
        $this->form->bind('foobar');

        $this->assertTrue($this->form->isSynchronized());
    }

    public function testNotSynchronizedIfTransformationFailed()
    {
        $transformer = $this->getDataTransformer();
        $transformer->expects($this->once())
            ->method('reverseTransform')
            ->will($this->throwException(new TransformationFailedException()));

        $form = $this->getBuilder()
            ->appendClientTransformer($transformer)
            ->getForm();

        $form->bind('foobar');

        $this->assertFalse($form->isSynchronized());
    }

    public function testEmptyDataCreatedBeforeTransforming()
    {
        $form = $this->getBuilder()
            ->setEmptyData('foo')
            ->appendClientTransformer(new FixedDataTransformer(array(
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
            ->appendClientTransformer(new FixedDataTransformer(array(
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
            ->appendClientTransformer(new FixedDataTransformer(array(
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
            ->appendClientTransformer(new FixedDataTransformer(array(
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
            ->appendClientTransformer(new FixedDataTransformer(array(
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
            array('DELETE'),
            array('PATCH'),
        );
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testBindPostOrPutRequest($method)
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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

    /**
     * @dataProvider requestMethodProvider
     */
    public function testBindPostOrPutRequestWithEmptyRootFormName($method)
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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

        $form = $this->getBuilder('')->getForm();
        $form->add($this->getBuilder('name')->getForm());
        $form->add($this->getBuilder('image')->getForm());

        $form->bindRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', 123, UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals($file, $form['image']->getData());
        $this->assertEquals(array('extra' => 'data'), $form->getExtraData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testBindPostOrPutRequestWithSingleChildForm($method)
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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

        $form = $this->getBuilder('image')->getForm();

        $form->bindRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', 123, UPLOAD_ERR_OK);

        $this->assertEquals($file, $form->getData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testBindPostOrPutRequestWithSingleChildFormUploadedFile($method)
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

        $values = array(
            'name' => 'Bernhard',
        );

        $request = new Request(array(), $values, array(), array(), array(), array(
            'REQUEST_METHOD' => $method,
        ));

        $form = $this->getBuilder('name')->getForm();

        $form->bindRequest($request);

        $this->assertEquals('Bernhard', $form->getData());

        unlink($path);
    }

    public function testBindGetRequest()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

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

    public function testBindGetRequestWithEmptyRootFormName()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

        $values = array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
            'extra' => 'data'
        );

        $request = new Request($values, array(), array(), array(), array(), array(
            'REQUEST_METHOD' => 'GET',
        ));

        $form = $this->getBuilder('')->getForm();
        $form->add($this->getBuilder('firstName')->getForm());
        $form->add($this->getBuilder('lastName')->getForm());

        $form->bindRequest($request);

        $this->assertEquals('Bernhard', $form['firstName']->getData());
        $this->assertEquals('Schussek', $form['lastName']->getData());
        $this->assertEquals(array('extra' => 'data'), $form->getExtraData());
    }

    public function testBindResetsErrors()
    {
        $form = $this->getBuilder()->getForm();
        $form->addError(new FormError('Error!'));
        $form->bind('foobar');

        $this->assertSame(array(), $form->getErrors());
    }

    public function testCreateView()
    {
        $test = $this;
        $type1 = $this->getMock('Symfony\Component\Form\FormTypeInterface');
        $type1Extension = $this->getMock('Symfony\Component\Form\FormTypeExtensionInterface');
        $type1->expects($this->any())
            ->method('getExtensions')
            ->will($this->returnValue(array($type1Extension)));
        $type2 = $this->getMock('Symfony\Component\Form\FormTypeInterface');
        $type2Extension = $this->getMock('Symfony\Component\Form\FormTypeExtensionInterface');
        $type2->expects($this->any())
            ->method('getExtensions')
            ->will($this->returnValue(array($type2Extension)));
        $calls = array();

        $type1->expects($this->once())
            ->method('buildView')
            ->will($this->returnCallback(function (FormView $view, Form $form) use ($test, &$calls) {
                $calls[] = 'type1::buildView';
                $test->assertTrue($view->hasParent());
                $test->assertFalse($view->hasChildren());
            }));

        $type1Extension->expects($this->once())
            ->method('buildView')
            ->will($this->returnCallback(function (FormView $view, Form $form) use ($test, &$calls) {
                $calls[] = 'type1ext::buildView';
                $test->assertTrue($view->hasParent());
                $test->assertFalse($view->hasChildren());
            }));

        $type2->expects($this->once())
            ->method('buildView')
            ->will($this->returnCallback(function (FormView $view, Form $form) use ($test, &$calls) {
                $calls[] = 'type2::buildView';
                $test->assertTrue($view->hasParent());
                $test->assertFalse($view->hasChildren());
            }));

        $type2Extension->expects($this->once())
            ->method('buildView')
            ->will($this->returnCallback(function (FormView $view, Form $form) use ($test, &$calls) {
                $calls[] = 'type2ext::buildView';
                $test->assertTrue($view->hasParent());
                $test->assertFalse($view->hasChildren());
            }));

        $type1->expects($this->once())
            ->method('buildViewBottomUp')
            ->will($this->returnCallback(function (FormView $view, Form $form) use ($test, &$calls) {
                $calls[] = 'type1::buildViewBottomUp';
                $test->assertTrue($view->hasChildren());
            }));

        $type1Extension->expects($this->once())
            ->method('buildViewBottomUp')
            ->will($this->returnCallback(function (FormView $view, Form $form) use ($test, &$calls) {
                $calls[] = 'type1ext::buildViewBottomUp';
                $test->assertTrue($view->hasChildren());
            }));

        $type2->expects($this->once())
            ->method('buildViewBottomUp')
            ->will($this->returnCallback(function (FormView $view, Form $form) use ($test, &$calls) {
                $calls[] = 'type2::buildViewBottomUp';
                $test->assertTrue($view->hasChildren());
            }));

        $type2Extension->expects($this->once())
            ->method('buildViewBottomUp')
            ->will($this->returnCallback(function (FormView $view, Form $form) use ($test, &$calls) {
                $calls[] = 'type2ext::buildViewBottomUp';
                $test->assertTrue($view->hasChildren());
            }));

        $form = $this->getBuilder()->setTypes(array($type1, $type2))->getForm();
        $form->setParent($this->getBuilder()->getForm());
        $form->add($this->getBuilder()->getForm());

        $form->createView();

        $this->assertEquals(array(
            0 => 'type1::buildView',
            1 => 'type1ext::buildView',
            2 => 'type2::buildView',
            3 => 'type2ext::buildView',
            4 => 'type1::buildViewBottomUp',
            5 => 'type1ext::buildViewBottomUp',
            6 => 'type2::buildViewBottomUp',
            7 => 'type2ext::buildViewBottomUp',
        ), $calls);
    }

    public function testCreateViewAcceptsParent()
    {
        $parent = new FormView('form');

        $form = $this->getBuilder()->getForm();
        $view = $form->createView($parent);

        $this->assertSame($parent, $view->getParent());
    }

    public function testGetErrorsAsString()
    {
        $form = $this->getBuilder()->getForm();
        $form->addError(new FormError('Error!'));

        $this->assertEquals("ERROR: Error!\n", $form->getErrorsAsString());
    }

    public function testGetErrorsAsStringDeep()
    {
        $form = $this->getBuilder()->getForm();
        $form->addError(new FormError('Error!'));

        $parent = $this->getBuilder()->getForm();
        $parent->add($form);

        $parent->add($this->getBuilder('foo')->getForm());

        $this->assertEquals("name:\n    ERROR: Error!\nfoo:\n    No errors\n", $parent->getErrorsAsString());
    }

    public function testFormCanHaveEmptyName()
    {
        $form = $this->getBuilder('')->getForm();

        $this->assertEquals('', $form->getName());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     * @expectedExceptionMessage Form with empty name can not have parent form.
     */
    public function testFormCannotHaveEmptyNameNotInRootLevel()
    {
        $parent = $this->getBuilder()
            ->add($this->getBuilder(''))
            ->getForm();
    }

    protected function getBuilder($name = 'name', EventDispatcherInterface $dispatcher = null)
    {
        return new FormBuilder($name, $this->factory, $dispatcher ?: $this->dispatcher);
    }

    protected function getMockForm($name = 'name')
    {
        $form = $this->getMock('Symfony\Component\Form\Tests\FormInterface');

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
        return $this->getMock('Symfony\Component\Form\DataMapperInterface');
    }

    protected function getDataTransformer()
    {
        return $this->getMock('Symfony\Component\Form\DataTransformerInterface');
    }

    protected function getFormValidator()
    {
        return $this->getMock('Symfony\Component\Form\FormValidatorInterface');
    }
}
