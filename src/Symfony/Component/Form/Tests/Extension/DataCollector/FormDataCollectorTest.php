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

use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormView;

class FormDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $dataExtractor;

    /**
     * @var FormDataCollector
     */
    private $dataCollector;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $dispatcher;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $factory;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $dataMapper;

    /**
     * @var Form
     */
    private $form;

    /**
     * @var Form
     */
    private $childForm;

    /**
     * @var FormView
     */
    private $view;

    /**
     * @var FormView
     */
    private $childView;

    protected function setUp()
    {
        $this->dataExtractor = $this->getMock('Symfony\Component\Form\Extension\DataCollector\FormDataExtractorInterface');
        $this->dataCollector = new FormDataCollector($this->dataExtractor);
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->dataMapper = $this->getMock('Symfony\Component\Form\DataMapperInterface');
        $this->form = $this->createForm('name');
        $this->childForm = $this->createForm('child');
        $this->view = new FormView();
        $this->childView = new FormView();
    }

    public function testBuildPreliminaryFormTree()
    {
        $this->form->add($this->childForm);

        $this->dataExtractor->expects($this->at(0))
            ->method('extractConfiguration')
            ->with($this->form)
            ->will($this->returnValue(array('config' => 'foo')));
        $this->dataExtractor->expects($this->at(1))
            ->method('extractConfiguration')
            ->with($this->childForm)
            ->will($this->returnValue(array('config' => 'bar')));

        $this->dataExtractor->expects($this->at(2))
            ->method('extractDefaultData')
            ->with($this->form)
            ->will($this->returnValue(array('default_data' => 'foo')));
        $this->dataExtractor->expects($this->at(3))
            ->method('extractDefaultData')
            ->with($this->childForm)
            ->will($this->returnValue(array('default_data' => 'bar')));

        $this->dataExtractor->expects($this->at(4))
            ->method('extractSubmittedData')
            ->with($this->form)
            ->will($this->returnValue(array('submitted_data' => 'foo')));
        $this->dataExtractor->expects($this->at(5))
            ->method('extractSubmittedData')
            ->with($this->childForm)
            ->will($this->returnValue(array('submitted_data' => 'bar')));

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->collectSubmittedData($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $childFormData = array(
             'config' => 'bar',
             'default_data' => 'bar',
             'submitted_data' => 'bar',
             'children' => array(),
         );

        $formData = array(
             'config' => 'foo',
             'default_data' => 'foo',
             'submitted_data' => 'foo',
             'has_children_error' => false,
             'children' => array(
                 'child' => $childFormData,
             ),
         );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                spl_object_hash($this->childForm) => $childFormData,
            ),
            'nb_errors' => 0,
         ), $this->dataCollector->getData());
    }

    public function testBuildMultiplePreliminaryFormTrees()
    {
        $form1 = $this->createForm('form1');
        $form2 = $this->createForm('form2');

        $this->dataExtractor->expects($this->at(0))
            ->method('extractConfiguration')
            ->with($form1)
            ->will($this->returnValue(array('config' => 'foo')));
        $this->dataExtractor->expects($this->at(1))
            ->method('extractConfiguration')
            ->with($form2)
            ->will($this->returnValue(array('config' => 'bar')));

        $this->dataCollector->collectConfiguration($form1);
        $this->dataCollector->collectConfiguration($form2);
        $this->dataCollector->buildPreliminaryFormTree($form1);

        $form1Data = array(
            'config' => 'foo',
            'children' => array(),
        );

        $this->assertSame(array(
            'forms' => array(
                'form1' => $form1Data,
            ),
            'forms_by_hash' => array(
                spl_object_hash($form1) => $form1Data,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());

        $this->dataCollector->buildPreliminaryFormTree($form2);

        $form2Data = array(
            'config' => 'bar',
            'children' => array(),
        );

        $this->assertSame(array(
            'forms' => array(
                'form1' => $form1Data,
                'form2' => $form2Data,
            ),
            'forms_by_hash' => array(
                spl_object_hash($form1) => $form1Data,
                spl_object_hash($form2) => $form2Data,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testBuildSamePreliminaryFormTreeMultipleTimes()
    {
        $this->dataExtractor->expects($this->at(0))
            ->method('extractConfiguration')
            ->with($this->form)
            ->will($this->returnValue(array('config' => 'foo')));

        $this->dataExtractor->expects($this->at(1))
            ->method('extractDefaultData')
            ->with($this->form)
            ->will($this->returnValue(array('default_data' => 'foo')));

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $formData = array(
            'config' => 'foo',
            'children' => array(),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());

        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $formData = array(
            'config' => 'foo',
            'default_data' => 'foo',
            'children' => array(),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testBuildPreliminaryFormTreeWithoutCollectingAnyData()
    {
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $formData = array(
            'children' => array(),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testBuildFinalFormTree()
    {
        $this->form->add($this->childForm);
        $this->view->children['child'] = $this->childView;

        $this->dataExtractor->expects($this->at(0))
            ->method('extractConfiguration')
            ->with($this->form)
            ->will($this->returnValue(array('config' => 'foo')));
        $this->dataExtractor->expects($this->at(1))
            ->method('extractConfiguration')
            ->with($this->childForm)
            ->will($this->returnValue(array('config' => 'bar')));

        $this->dataExtractor->expects($this->at(2))
            ->method('extractDefaultData')
            ->with($this->form)
            ->will($this->returnValue(array('default_data' => 'foo')));
        $this->dataExtractor->expects($this->at(3))
            ->method('extractDefaultData')
            ->with($this->childForm)
            ->will($this->returnValue(array('default_data' => 'bar')));

        $this->dataExtractor->expects($this->at(4))
            ->method('extractSubmittedData')
            ->with($this->form)
            ->will($this->returnValue(array('submitted_data' => 'foo')));
        $this->dataExtractor->expects($this->at(5))
            ->method('extractSubmittedData')
            ->with($this->childForm)
            ->will($this->returnValue(array('submitted_data' => 'bar')));

        $this->dataExtractor->expects($this->at(6))
            ->method('extractViewVariables')
            ->with($this->view)
            ->will($this->returnValue(array('view_vars' => 'foo')));

        $this->dataExtractor->expects($this->at(7))
            ->method('extractViewVariables')
            ->with($this->childView)
            ->will($this->returnValue(array('view_vars' => 'bar')));

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->collectSubmittedData($this->form);
        $this->dataCollector->collectViewVariables($this->view);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = array(
            'view_vars' => 'bar',
            'config' => 'bar',
            'default_data' => 'bar',
            'submitted_data' => 'bar',
            'children' => array(),
        );

        $formData = array(
            'view_vars' => 'foo',
            'config' => 'foo',
            'default_data' => 'foo',
            'submitted_data' => 'foo',
            'has_children_error' => false,
            'children' => array(
                'child' => $childFormData,
            ),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                spl_object_hash($this->childForm) => $childFormData,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testFinalFormReliesOnFormViewStructure()
    {
        $this->form->add($child1 = $this->createForm('first'));
        $this->form->add($child2 = $this->createForm('second'));

        $this->view->children['second'] = $this->childView;

        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $child1Data = array(
            'children' => array(),
        );

        $child2Data = array(
            'children' => array(),
        );

        $formData = array(
            'children' => array(
                'first' => $child1Data,
                'second' => $child2Data,
            ),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                spl_object_hash($child1) => $child1Data,
                spl_object_hash($child2) => $child2Data,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());

        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $formData = array(
            'children' => array(
                // "first" not present in FormView
                'second' => $child2Data,
            ),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                spl_object_hash($child1) => $child1Data,
                spl_object_hash($child2) => $child2Data,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testChildViewsCanBeWithoutCorrespondingChildForms()
    {
        // don't add $this->childForm to $this->form!

        $this->view->children['child'] = $this->childView;

        $this->dataExtractor->expects($this->at(0))
            ->method('extractConfiguration')
            ->with($this->form)
            ->will($this->returnValue(array('config' => 'foo')));
        $this->dataExtractor->expects($this->at(1))
            ->method('extractConfiguration')
            ->with($this->childForm)
            ->will($this->returnValue(array('config' => 'bar')));

        // explicitly call collectConfiguration(), since $this->childForm is not
        // contained in the form tree
        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectConfiguration($this->childForm);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = array(
            // no "config" key
            'children' => array(),
        );

        $formData = array(
            'config' => 'foo',
            'children' => array(
                'child' => $childFormData,
            ),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                // no child entry
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testChildViewsWithoutCorrespondingChildFormsMayBeExplicitlyAssociated()
    {
        // don't add $this->childForm to $this->form!

        $this->view->children['child'] = $this->childView;

        // but associate the two
        $this->dataCollector->associateFormWithView($this->childForm, $this->childView);

        $this->dataExtractor->expects($this->at(0))
            ->method('extractConfiguration')
            ->with($this->form)
            ->will($this->returnValue(array('config' => 'foo')));
        $this->dataExtractor->expects($this->at(1))
            ->method('extractConfiguration')
            ->with($this->childForm)
            ->will($this->returnValue(array('config' => 'bar')));

        // explicitly call collectConfiguration(), since $this->childForm is not
        // contained in the form tree
        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectConfiguration($this->childForm);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = array(
            'config' => 'bar',
            'children' => array(),
        );

        $formData = array(
            'config' => 'foo',
            'children' => array(
                'child' => $childFormData,
            ),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                spl_object_hash($this->childForm) => $childFormData,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testCollectSubmittedDataCountsErrors()
    {
        $form1 = $this->createForm('form1');
        $childForm1 = $this->createForm('child1');
        $form2 = $this->createForm('form2');

        $form1->add($childForm1);
        $this->dataExtractor
             ->method('extractConfiguration')
             ->will($this->returnValue(array()));
        $this->dataExtractor
             ->method('extractDefaultData')
             ->will($this->returnValue(array()));
        $this->dataExtractor->expects($this->at(4))
            ->method('extractSubmittedData')
            ->with($form1)
            ->will($this->returnValue(array('errors' => array('foo'))));
        $this->dataExtractor->expects($this->at(5))
            ->method('extractSubmittedData')
            ->with($childForm1)
            ->will($this->returnValue(array('errors' => array('bar', 'bam'))));
        $this->dataExtractor->expects($this->at(8))
            ->method('extractSubmittedData')
            ->with($form2)
            ->will($this->returnValue(array('errors' => array('baz'))));

        $this->dataCollector->collectSubmittedData($form1);

        $data = $this->dataCollector->getData();
        $this->assertSame(3, $data['nb_errors']);

        $this->dataCollector->collectSubmittedData($form2);

        $data = $this->dataCollector->getData();
        $this->assertSame(4, $data['nb_errors']);
    }

    public function testCollectSubmittedDataExpandedFormsErrors()
    {
        $child1Form = $this->createForm('child1');
        $child11Form = $this->createForm('child11');
        $child2Form = $this->createForm('child2');
        $child21Form = $this->createForm('child21');

        $child1Form->add($child11Form);
        $child2Form->add($child21Form);
        $this->form->add($child1Form);
        $this->form->add($child2Form);

        $this->dataExtractor
            ->method('extractConfiguration')
            ->will($this->returnValue(array()));
        $this->dataExtractor
            ->method('extractDefaultData')
            ->will($this->returnValue(array()));
        $this->dataExtractor->expects($this->at(10))
            ->method('extractSubmittedData')
            ->with($this->form)
            ->will($this->returnValue(array('errors' => array())));
        $this->dataExtractor->expects($this->at(11))
            ->method('extractSubmittedData')
            ->with($child1Form)
            ->will($this->returnValue(array('errors' => array())));
        $this->dataExtractor->expects($this->at(12))
            ->method('extractSubmittedData')
            ->with($child11Form)
            ->will($this->returnValue(array('errors' => array('foo'))));
        $this->dataExtractor->expects($this->at(13))
            ->method('extractSubmittedData')
            ->with($child2Form)
            ->will($this->returnValue(array('errors' => array())));
        $this->dataExtractor->expects($this->at(14))
            ->method('extractSubmittedData')
            ->with($child21Form)
            ->will($this->returnValue(array('errors' => array())));

        $this->dataCollector->collectSubmittedData($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $data = $this->dataCollector->getData();
        $formData = $data['forms']['name'];
        $child1Data = $formData['children']['child1'];
        $child11Data = $child1Data['children']['child11'];
        $child2Data = $formData['children']['child2'];
        $child21Data = $child2Data['children']['child21'];

        $this->assertTrue($formData['has_children_error']);
        $this->assertTrue($child1Data['has_children_error']);
        $this->assertFalse(isset($child11Data['has_children_error']), 'The leaf data does not contains "has_children_error" property.');
        $this->assertFalse($child2Data['has_children_error']);
        $this->assertFalse(isset($child21Data['has_children_error']), 'The leaf data does not contains "has_children_error" property.');
    }

    private function createForm($name)
    {
        $builder = new FormBuilder($name, null, $this->dispatcher, $this->factory);
        $builder->setCompound(true);
        $builder->setDataMapper($this->dataMapper);

        return $builder->getForm();
    }
}
