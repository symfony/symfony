<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Profiler;

use Symfony\Component\Form\Extension\Profiler\FormDataCollector;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Profiler\FormData;

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
        $this->dataExtractor = $this->getMock('Symfony\Component\Form\Extension\Profiler\FormDataExtractorInterface');
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

        $this->dataCollector->postSetData(new FormEvent($this->form, array()));
        $this->dataCollector->postSubmit(new FormEvent($this->form, array()));

        $childFormData = array(
            'name' => 'child',
            'children' => array(),
            'config' => 'bar',
            'default_data' => 'bar',
            'submitted_data' => 'bar',
         );

        $formData = array(
            'name' => 'name',
            'children' => array(
                spl_object_hash($this->childForm) => $childFormData,
            ),
            'config' => 'foo',
            'default_data' => 'foo',
            'submitted_data' => 'foo',
         );

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();

        $this->assertSame(array(
            spl_object_hash($this->form) => $formData,
        ), $profileData->getForms());
        $this->assertSame(0, $profileData->getNbErrors());
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
            ->method('extractDefaultData')
            ->with($form1)
            ->will($this->returnValue(array('default_data' => 'foo')));

        $this->dataExtractor->expects($this->at(2))
            ->method('extractConfiguration')
            ->with($form2)
            ->will($this->returnValue(array('config' => 'bar')));

        $this->dataExtractor->expects($this->at(3))
            ->method('extractDefaultData')
            ->with($form2)
            ->will($this->returnValue(array('default_data' => 'bar')));

        $this->dataExtractor->expects($this->at(4))
            ->method('extractSubmittedData')
            ->with($form1)
            ->will($this->returnValue(array('submitted_data' => 'foo')));

        $this->dataExtractor->expects($this->at(5))
            ->method('extractSubmittedData')
            ->with($form2)
            ->will($this->returnValue(array('submitted_data' => 'bar')));

        $this->dataCollector->postSetData(new FormEvent($form1, array()));
        $this->dataCollector->postSetData(new FormEvent($form2, array()));
        $this->dataCollector->postSubmit(new FormEvent($form1, array()));

        $form1Data = array(
            'name' => 'form1',
            'children' => array(),
            'config' => 'foo',
            'default_data' => 'foo',
            'submitted_data' => 'foo',
        );

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();
        $this->assertSame(
            array(
                spl_object_hash($form1) => $form1Data,
            ),
            $profileData->getForms()
        );

        $this->dataCollector->postSubmit(new FormEvent($form2, array()));

        $form2Data = array(
            'name' => 'form2',
            'children' => array(),
            'config' => 'bar',
            'default_data' => 'bar',
            'submitted_data' => 'bar',
        );

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();
        $this->assertSame(
            array(
                spl_object_hash($form1) => $form1Data,
                spl_object_hash($form2) => $form2Data,
            ),
            $profileData->getForms()
        );

        $this->assertSame(0, $profileData->getNbErrors());
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

        $this->dataExtractor->expects($this->at(2))
            ->method('extractSubmittedData')
            ->with($this->form)
            ->will($this->returnValue(array('submitted_data' => 'foo')));

        $this->dataExtractor->expects($this->at(3))
            ->method('extractSubmittedData')
            ->with($this->form)
            ->will($this->returnValue(array('submitted_data' => 'foo')));


        $this->dataCollector->postSetData(new FormEvent($this->form, array()));
        $this->dataCollector->postSubmit(new FormEvent($this->form, array()));

        $formData = array(
            'name' => 'name',
            'children' => array(),
            'config' => 'foo',
            'default_data' => 'foo',
            'submitted_data' => 'foo',
        );

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();

        $this->assertSame(
            array(
                spl_object_hash($this->form) => $formData,
            ),
            $profileData->getForms()
        );

        $this->dataCollector->postSubmit(new FormEvent($this->form, array()));


        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();

        $formData = array(
            'name' => 'name',
            'children' => array(),
            'config' => 'foo',
            'default_data' => 'foo',
            'submitted_data' => 'foo',
        );

        $this->assertSame(
            array(
                spl_object_hash($this->form) => $formData,
            ),
            $profileData->getForms()
        );
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

        $this->dataCollector->postSetData(new FormEvent($this->form, array()));
        $this->dataCollector->postSubmit(new FormEvent($this->form, array()));
        $this->dataCollector->collectViewVariables($this->view);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = array(
            'name' => 'child',
            'children' => array(),
            'view_vars' => 'bar',
            'config' => 'bar',
            'default_data' => 'bar',
            'submitted_data' => 'bar',
        );

        $formData = array(
            'name' => 'name',
            'children' => array(
                spl_object_hash($this->childForm) => $childFormData,
            ),
            'view_vars' => 'foo',
            'config' => 'foo',
            'default_data' => 'foo',
            'submitted_data' => 'foo',
        );

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();

        $this->assertSame(
            array(
                spl_object_hash($this->form) => $formData,
            ),
            $profileData->getForms()
        );
    }

    public function testFinalFormReliesOnFormViewStructure()
    {
        $this->form->add($child1 = $this->createForm('first'));
        $this->form->add($child2 = $this->createForm('second'));

        $this->dataExtractor->expects($this->at(0))
            ->method('extractConfiguration')
            ->with($this->form)
            ->will($this->returnValue(array('config' => 'foo')));
        $this->dataExtractor->expects($this->at(1))
            ->method('extractConfiguration')
            ->with($child1)
            ->will($this->returnValue(array('config' => 'bar')));
        $this->dataExtractor->expects($this->at(2))
            ->method('extractConfiguration')
            ->with($child2)
            ->will($this->returnValue(array('config' => 'saa')));

        $this->dataExtractor->expects($this->at(3))
            ->method('extractDefaultData')
            ->with($this->form)
            ->will($this->returnValue(array('default_data' => 'foo')));
        $this->dataExtractor->expects($this->at(4))
            ->method('extractDefaultData')
            ->with($child1)
            ->will($this->returnValue(array('default_data' => 'bar')));
        $this->dataExtractor->expects($this->at(5))
            ->method('extractDefaultData')
            ->with($child2)
            ->will($this->returnValue(array('default_data' => 'saa')));

        $this->dataExtractor->expects($this->at(6))
            ->method('extractSubmittedData')
            ->with($this->form)
            ->will($this->returnValue(array('submitted_data' => 'foo')));
        $this->dataExtractor->expects($this->at(7))
            ->method('extractSubmittedData')
            ->with($child1)
            ->will($this->returnValue(array('submitted_data' => 'bar')));
        $this->dataExtractor->expects($this->at(8))
            ->method('extractSubmittedData')
            ->with($child2)
            ->will($this->returnValue(array('submitted_data' => 'saa')));

        $this->view->children['second'] = $this->childView;

        $this->dataCollector->postSubmit(new FormEvent($this->form, array()));

        $child1Data = array(
            'name' => 'first',
            'children' => array(),
            'config' => 'bar',
            'default_data' => 'bar',
            'submitted_data' => 'bar'
        );

        $child2Data = array(
            'name' => 'second',
            'children' => array(),
            'config' => 'saa',
            'default_data' => 'saa',
            'submitted_data' => 'saa'
        );

        $formData = array(
            'name' => 'name',
            'children' => array(),
            'config' => 'foo',
            'default_data' => 'foo',
            'submitted_data' => 'foo'
        );

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();
        $this->assertSame(
            array(
                spl_object_hash($this->form) => array_replace(
                    $formData,
                    array(
                        'children' => array(
                            spl_object_hash($child1) => $child1Data,
                            spl_object_hash($child2) => $child2Data,
                        )
                    )
                ),
            ),
            $profileData->getForms()
        );

        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();
        $this->assertSame(
            array(
                spl_object_hash($this->form) => array_replace(
                    $formData,
                    array(
                        'children' => array(
                            // "first" not present in FormView
                            spl_object_hash($child2) => $child2Data,
                        )
                    )
                ),
            ),
            $profileData->getForms()
        );
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
            ->method('extractDefaultData')
            ->with($this->form)
            ->will($this->returnValue(array('default_data' => 'foo')));

        $this->dataExtractor->expects($this->at(2))
            ->method('extractConfiguration')
            ->with($this->childForm)
            ->will($this->returnValue(array('config' => 'bar')));

        $this->dataExtractor->expects($this->at(3))
            ->method('extractDefaultData')
            ->with($this->childForm)
            ->will($this->returnValue(array('default_data' => 'bar')));

        // explicitly call collectConfiguration(), since $this->childForm is not
        // contained in the form tree
        $this->dataCollector->postSetData(new FormEvent($this->form, array()));
        $this->dataCollector->postSetData(new FormEvent($this->childForm, array()));
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();

        $childFormData = array(
            'name' => 'child',
            // no "config" key
            'children' => array(),
        );

        $formData = array(
            'name' => 'name',
            'children' => array(
                spl_object_hash($this->childView) => $childFormData,
            ),
            'config' => 'foo',
            'default_data' => 'foo'
        );

        $this->assertSame(
            array(
                spl_object_hash($this->form) => $formData,
            ),
            $profileData->getForms()
        );
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
            ->method('extractDefaultData')
            ->with($this->form)
            ->will($this->returnValue(array('default_data' => 'foo')));

        $this->dataExtractor->expects($this->at(2))
            ->method('extractConfiguration')
            ->with($this->childForm)
            ->will($this->returnValue(array('config' => 'bar')));

        $this->dataExtractor->expects($this->at(3))
            ->method('extractDefaultData')
            ->with($this->childForm)
            ->will($this->returnValue(array('default_data' => 'bar')));

        // explicitly call collectConfiguration(), since $this->childForm is not
        // contained in the form tree
        $this->dataCollector->postSetData(new FormEvent($this->form, array()));
        $this->dataCollector->postSetData(new FormEvent($this->childForm, array()));
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = array(
            'name' => 'child',
            'children' => array(),
            'config' => 'bar',
            'default_data' => 'bar',
        );

        $formData = array(
            'name' => 'name',
            'children' => array(
                spl_object_hash($this->childView) => $childFormData,
            ),
            'config' => 'foo',
            'default_data' => 'foo',
        );

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();

        $this->assertSame(
            array(
                spl_object_hash($this->form) => $formData,
            ),
            $profileData->getForms()
        );
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

        $this->dataCollector->postSubmit(new FormEvent($form1, array()));

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();
        $this->assertSame(3, $profileData->getNbErrors());

        $this->dataCollector->postSubmit(new FormEvent($form2, array()));

        /** @var FormData $profileData */
        $profileData = $this->dataCollector->getCollectedData();
        $this->assertSame(4, $profileData->getNbErrors());

    }

    private function createForm($name)
    {
        $builder = new FormBuilder($name, null, $this->dispatcher, $this->factory);
        $builder->setCompound(true);
        $builder->setDataMapper($this->dataMapper);

        return $builder->getForm();
    }
}
