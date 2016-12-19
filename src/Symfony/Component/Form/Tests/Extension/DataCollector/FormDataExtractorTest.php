<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\DataCollector;

use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\DataCollector\FormDataExtractor;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormDataExtractorTest extends \PHPUnit_Framework_TestCase
{
    use VarDumperTestTrait;

    /**
     * @var FormDataExtractor
     */
    private $dataExtractor;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $dispatcher;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $factory;

    protected function setUp()
    {
        $this->dataExtractor = new FormDataExtractor();
        $this->dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->factory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
    }

    public function testExtractConfiguration()
    {
        $type = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeInterface')->getMock();
        $type->expects($this->any())
            ->method('getInnerType')
            ->will($this->returnValue(new \stdClass()));

        $form = $this->createBuilder('name')
            ->setType($type)
            ->getForm();

        $this->assertSame(array(
            'id' => 'name',
            'name' => 'name',
            'type_class' => 'stdClass',
            'synchronized' => true,
            'passed_options' => array(),
            'resolved_options' => array(),
        ), $this->dataExtractor->extractConfiguration($form));
    }

    public function testExtractConfigurationSortsPassedOptions()
    {
        $type = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeInterface')->getMock();
        $type->expects($this->any())
            ->method('getInnerType')
            ->will($this->returnValue(new \stdClass()));

        $options = array(
            'b' => 'foo',
            'a' => 'bar',
            'c' => 'baz',
        );

        $form = $this->createBuilder('name')
            ->setType($type)
            // passed options are stored in an attribute by
            // ResolvedTypeDataCollectorProxy
            ->setAttribute('data_collector/passed_options', $options)
            ->getForm();

        $this->assertSame(array(
            'id' => 'name',
            'name' => 'name',
            'type_class' => 'stdClass',
            'synchronized' => true,
            'passed_options' => array(
                'a' => 'bar',
                'b' => 'foo',
                'c' => 'baz',
            ),
            'resolved_options' => array(),
        ), $this->dataExtractor->extractConfiguration($form));
    }

    public function testExtractConfigurationSortsResolvedOptions()
    {
        $type = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeInterface')->getMock();
        $type->expects($this->any())
            ->method('getInnerType')
            ->will($this->returnValue(new \stdClass()));

        $options = array(
            'b' => 'foo',
            'a' => 'bar',
            'c' => 'baz',
        );

        $form = $this->createBuilder('name', $options)
            ->setType($type)
            ->getForm();

        $this->assertSame(array(
            'id' => 'name',
            'name' => 'name',
            'type_class' => 'stdClass',
            'synchronized' => true,
            'passed_options' => array(),
            'resolved_options' => array(
                'a' => 'bar',
                'b' => 'foo',
                'c' => 'baz',
            ),
        ), $this->dataExtractor->extractConfiguration($form));
    }

    public function testExtractConfigurationBuildsIdRecursively()
    {
        $type = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeInterface')->getMock();
        $type->expects($this->any())
            ->method('getInnerType')
            ->will($this->returnValue(new \stdClass()));

        $grandParent = $this->createBuilder('grandParent')
            ->setCompound(true)
            ->setDataMapper($this->getMockBuilder('Symfony\Component\Form\DataMapperInterface')->getMock())
            ->getForm();
        $parent = $this->createBuilder('parent')
            ->setCompound(true)
            ->setDataMapper($this->getMockBuilder('Symfony\Component\Form\DataMapperInterface')->getMock())
            ->getForm();
        $form = $this->createBuilder('name')
            ->setType($type)
            ->getForm();

        $grandParent->add($parent);
        $parent->add($form);

        $this->assertSame(array(
            'id' => 'grandParent_parent_name',
            'name' => 'name',
            'type_class' => 'stdClass',
            'synchronized' => true,
            'passed_options' => array(),
            'resolved_options' => array(),
        ), $this->dataExtractor->extractConfiguration($form));
    }

    public function testExtractDefaultData()
    {
        $form = $this->createBuilder('name')->getForm();

        $form->setData('Foobar');

        $this->assertSame(array(
            'default_data' => array(
                'norm' => 'Foobar',
            ),
            'submitted_data' => array(),
        ), $this->dataExtractor->extractDefaultData($form));
    }

    public function testExtractDefaultDataStoresModelDataIfDifferent()
    {
        $form = $this->createBuilder('name')
            ->addModelTransformer(new FixedDataTransformer(array(
                'Foo' => 'Bar',
            )))
            ->getForm();

        $form->setData('Foo');

        $this->assertSame(array(
            'default_data' => array(
                'norm' => 'Bar',
                'model' => 'Foo',
            ),
            'submitted_data' => array(),
        ), $this->dataExtractor->extractDefaultData($form));
    }

    public function testExtractDefaultDataStoresViewDataIfDifferent()
    {
        $form = $this->createBuilder('name')
            ->addViewTransformer(new FixedDataTransformer(array(
                'Foo' => 'Bar',
            )))
            ->getForm();

        $form->setData('Foo');

        $this->assertSame(array(
            'default_data' => array(
                'norm' => 'Foo',
                'view' => 'Bar',
            ),
            'submitted_data' => array(),
        ), $this->dataExtractor->extractDefaultData($form));
    }

    public function testExtractSubmittedData()
    {
        $form = $this->createBuilder('name')->getForm();

        $form->submit('Foobar');

        $this->assertSame(array(
            'submitted_data' => array(
                'norm' => 'Foobar',
            ),
            'errors' => array(),
            'synchronized' => true,
        ), $this->dataExtractor->extractSubmittedData($form));
    }

    public function testExtractSubmittedDataStoresModelDataIfDifferent()
    {
        $form = $this->createBuilder('name')
            ->addModelTransformer(new FixedDataTransformer(array(
                'Foo' => 'Bar',
                '' => '',
            )))
            ->getForm();

        $form->submit('Bar');

        $this->assertSame(array(
            'submitted_data' => array(
                'norm' => 'Bar',
                'model' => 'Foo',
            ),
            'errors' => array(),
            'synchronized' => true,
        ), $this->dataExtractor->extractSubmittedData($form));
    }

    public function testExtractSubmittedDataStoresViewDataIfDifferent()
    {
        $form = $this->createBuilder('name')
            ->addViewTransformer(new FixedDataTransformer(array(
                'Foo' => 'Bar',
                '' => '',
            )))
            ->getForm();

        $form->submit('Bar');

        $this->assertSame(array(
            'submitted_data' => array(
                'norm' => 'Foo',
                'view' => 'Bar',
            ),
            'errors' => array(),
            'synchronized' => true,
        ), $this->dataExtractor->extractSubmittedData($form));
    }

    public function testExtractSubmittedDataStoresErrors()
    {
        $form = $this->createBuilder('name')->getForm();

        $form->submit('Foobar');
        $form->addError(new FormError('Invalid!'));

        $this->assertSame(array(
            'submitted_data' => array(
                'norm' => 'Foobar',
            ),
            'errors' => array(
                array('message' => 'Invalid!', 'origin' => spl_object_hash($form), 'trace' => array()),
            ),
            'synchronized' => true,
        ), $this->dataExtractor->extractSubmittedData($form));
    }

    public function testExtractSubmittedDataStoresErrorOrigin()
    {
        $form = $this->createBuilder('name')->getForm();

        $error = new FormError('Invalid!');
        $error->setOrigin($form);

        $form->submit('Foobar');
        $form->addError($error);

        $this->assertSame(array(
            'submitted_data' => array(
                'norm' => 'Foobar',
            ),
            'errors' => array(
                array('message' => 'Invalid!', 'origin' => spl_object_hash($form), 'trace' => array()),
            ),
            'synchronized' => true,
        ), $this->dataExtractor->extractSubmittedData($form));
    }

    public function testExtractSubmittedDataStoresErrorCause()
    {
        $form = $this->createBuilder('name')->getForm();

        $exception = new \Exception();
        $violation = new ConstraintViolation('Foo', 'Foo', array(), 'Root', 'property.path', 'Invalid!', null, null, null, $exception);

        $form->submit('Foobar');
        $form->addError(new FormError('Invalid!', null, array(), null, $violation));
        $origin = spl_object_hash($form);

        $this->assertDumpMatchesFormat(<<<EODUMP
array:3 [
  "submitted_data" => array:1 [
    "norm" => "Foobar"
  ]
  "errors" => array:1 [
    0 => array:3 [
      "message" => "Invalid!"
      "origin" => "$origin"
      "trace" => array:2 [
        0 => Symfony\Component\Validator\ConstraintViolation {
          -message: "Foo"
          -messageTemplate: "Foo"
          -parameters: []
          -plural: null
          -root: "Root"
          -propertyPath: "property.path"
          -invalidValue: "Invalid!"
          -constraint: null
          -code: null
          -cause: Exception {%A}
        }
        1 => Exception {#1}
      ]
    ]
  ]
  "synchronized" => true
]
EODUMP
            ,
            $this->dataExtractor->extractSubmittedData($form)
        );
    }

    public function testExtractSubmittedDataRemembersIfNonSynchronized()
    {
        $form = $this->createBuilder('name')
            ->addModelTransformer(new CallbackTransformer(
                function () {},
                function () {
                    throw new TransformationFailedException('Fail!');
                }
            ))
            ->getForm();

        $form->submit('Foobar');

        $this->assertSame(array(
            'submitted_data' => array(
                'norm' => 'Foobar',
                'model' => null,
            ),
            'errors' => array(),
            'synchronized' => false,
        ), $this->dataExtractor->extractSubmittedData($form));
    }

    public function testExtractViewVariables()
    {
        $view = new FormView();

        $view->vars = array(
            'b' => 'foo',
            'a' => 'bar',
            'c' => 'baz',
            'id' => 'foo_bar',
            'name' => 'bar',
        );

        $this->assertSame(array(
            'id' => 'foo_bar',
            'name' => 'bar',
            'view_vars' => array(
                'a' => 'bar',
                'b' => 'foo',
                'c' => 'baz',
                'id' => 'foo_bar',
                'name' => 'bar',
            ),
        ), $this->dataExtractor->extractViewVariables($view));
    }

    /**
     * @param string $name
     * @param array  $options
     *
     * @return FormBuilder
     */
    private function createBuilder($name, array $options = array())
    {
        return new FormBuilder($name, null, $this->dispatcher, $this->factory, $options);
    }
}
