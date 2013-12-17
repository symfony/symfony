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

        $this->assertSame(array(
             'forms' => array(
                 'name' => array(
                     'config' => 'foo',
                     'default_data' => 'foo',
                     'submitted_data' => 'foo',
                     'children' => array(
                         'child' => array(
                             'config' => 'bar',
                             'default_data' => 'bar',
                             'submitted_data' => 'bar',
                             'children' => array(),
                         ),
                     ),
                 ),
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

        $this->assertSame(array(
            'forms' => array(
                'form1' => array(
                    'config' => 'foo',
                    'children' => array(),
                ),
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());

        $this->dataCollector->buildPreliminaryFormTree($form2);

        $this->assertSame(array(
            'forms' => array(
                'form1' => array(
                    'config' => 'foo',
                    'children' => array(),
                ),
                'form2' => array(
                    'config' => 'bar',
                    'children' => array(),
                ),
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

        $this->assertSame(array(
            'forms' => array(
                'name' => array(
                    'config' => 'foo',
                    'children' => array(),
                ),
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());

        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $this->assertSame(array(
            'forms' => array(
                'name' => array(
                    'config' => 'foo',
                    'default_data' => 'foo',
                    'children' => array(),
                ),
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testBuildPreliminaryFormTreeWithoutCollectingAnyData()
    {
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $this->assertSame(array(
            'forms' => array(
                'name' => array(
                    'children' => array(),
                ),
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

        $this->assertSame(array(
            'forms' => array(
                'name' => array(
                    'view_vars' => 'foo',
                    'config' => 'foo',
                    'default_data' => 'foo',
                    'submitted_data' => 'foo',
                    'children' => array(
                        'child' => array(
                            'view_vars' => 'bar',
                            'config' => 'bar',
                            'default_data' => 'bar',
                            'submitted_data' => 'bar',
                            'children' => array(),
                        ),
                    ),
                ),
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testFinalFormReliesOnFormViewStructure()
    {
        $this->form->add($this->createForm('first'));
        $this->form->add($this->createForm('second'));

        $this->view->children['second'] = $this->childView;

        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $this->assertSame(array(
            'forms' => array(
                'name' => array(
                    'children' => array(
                        'first' => array(
                            'children' => array(),
                        ),
                        'second' => array(
                            'children' => array(),
                        ),
                    ),
                ),
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());

        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $this->assertSame(array(
            'forms' => array(
                'name' => array(
                    'children' => array(
                        // "first" not present in FormView
                        'second' => array(
                            'children' => array(),
                        ),
                    ),
                ),
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

        $this->assertSame(array(
            'forms' => array(
                'name' => array(
                    'config' => 'foo',
                    'children' => array(
                        'child' => array(
                            // no "config" key
                            'children' => array(),
                        ),
                    ),
                ),
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

        $this->assertSame(array(
            'forms' => array(
                'name' => array(
                    'config' => 'foo',
                    'children' => array(
                        'child' => array(
                            'config' => 'bar',
                            'children' => array(),
                        ),
                    ),
                ),
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

        $this->dataExtractor->expects($this->at(0))
            ->method('extractSubmittedData')
            ->with($form1)
            ->will($this->returnValue(array('errors' => array('foo'))));
        $this->dataExtractor->expects($this->at(1))
            ->method('extractSubmittedData')
            ->with($childForm1)
            ->will($this->returnValue(array('errors' => array('bar', 'bam'))));
        $this->dataExtractor->expects($this->at(2))
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

    private function createForm($name)
    {
        $builder = new FormBuilder($name, null, $this->dispatcher, $this->factory);
        $builder->setCompound(true);
        $builder->setDataMapper($this->dataMapper);

        return $builder->getForm();
    }
}
