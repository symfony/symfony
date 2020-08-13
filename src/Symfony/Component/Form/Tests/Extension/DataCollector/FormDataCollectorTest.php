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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ResolvedFormTypeFactory;

class FormDataCollectorTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $dataExtractor;

    /**
     * @var FormDataCollector
     */
    private $dataCollector;

    /**
     * @var MockObject
     */
    private $dispatcher;

    /**
     * @var MockObject
     */
    private $factory;

    /**
     * @var MockObject
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
        $this->dataExtractor = $this->getMockBuilder('Symfony\Component\Form\Extension\DataCollector\FormDataExtractorInterface')->getMock();
        $this->dataCollector = new FormDataCollector($this->dataExtractor);
        $this->dispatcher = new EventDispatcher();
        $this->factory = new FormFactory(new FormRegistry([new CoreExtension()], new ResolvedFormTypeFactory()));
        $this->dataMapper = new PropertyPathMapper();
        $this->form = $this->createForm('name');
        $this->childForm = $this->createForm('child');
        $this->view = new FormView();
        $this->childView = new FormView();
    }

    public function testBuildPreliminaryFormTree()
    {
        $this->form->add($this->childForm);

        $this->dataExtractor->expects($this->exactly(2))
            ->method('extractConfiguration')
            ->withConsecutive(
                [$this->form],
                [$this->childForm]
            )
            ->willReturnOnConsecutiveCalls(
                ['config' => 'foo'],
                ['config' => 'bar']
            );

        $this->dataExtractor->expects($this->exactly(2))
            ->method('extractDefaultData')
            ->withConsecutive(
                [$this->form],
                [$this->childForm]
            )
            ->willReturnOnConsecutiveCalls(
                ['default_data' => 'foo'],
                ['default_data' => 'bar']
            );

        $this->dataExtractor->expects($this->exactly(2))
            ->method('extractSubmittedData')
            ->withConsecutive(
                [$this->form],
                [$this->childForm]
            )
            ->willReturnOnConsecutiveCalls(
                ['submitted_data' => 'foo'],
                ['submitted_data' => 'bar']
            );

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->collectSubmittedData($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $childFormData = [
             'config' => 'bar',
             'default_data' => 'bar',
             'submitted_data' => 'bar',
             'children' => [],
         ];

        $formData = [
             'config' => 'foo',
             'default_data' => 'foo',
             'submitted_data' => 'foo',
             'has_children_error' => false,
             'children' => [
                 'child' => $childFormData,
             ],
         ];

        $this->assertSame([
            'forms' => [
                'name' => $formData,
            ],
            'forms_by_hash' => [
                spl_object_hash($this->form) => $formData,
                spl_object_hash($this->childForm) => $childFormData,
            ],
            'nb_errors' => 0,
         ], $this->dataCollector->getData());
    }

    public function testBuildMultiplePreliminaryFormTrees()
    {
        $form1 = $this->createForm('form1');
        $form2 = $this->createForm('form2');

        $this->dataExtractor->expects($this->exactly(2))
            ->method('extractConfiguration')
            ->withConsecutive(
                [$form1],
                [$form2]
            )
            ->willReturnOnConsecutiveCalls(
                ['config' => 'foo'],
                ['config' => 'bar']
            );

        $this->dataCollector->collectConfiguration($form1);
        $this->dataCollector->collectConfiguration($form2);
        $this->dataCollector->buildPreliminaryFormTree($form1);

        $form1Data = [
            'config' => 'foo',
            'children' => [],
        ];

        $this->assertSame([
            'forms' => [
                'form1' => $form1Data,
            ],
            'forms_by_hash' => [
                spl_object_hash($form1) => $form1Data,
            ],
            'nb_errors' => 0,
        ], $this->dataCollector->getData());

        $this->dataCollector->buildPreliminaryFormTree($form2);

        $form2Data = [
            'config' => 'bar',
            'children' => [],
        ];

        $this->assertSame([
            'forms' => [
                'form1' => $form1Data,
                'form2' => $form2Data,
            ],
            'forms_by_hash' => [
                spl_object_hash($form1) => $form1Data,
                spl_object_hash($form2) => $form2Data,
            ],
            'nb_errors' => 0,
        ], $this->dataCollector->getData());
    }

    public function testBuildSamePreliminaryFormTreeMultipleTimes()
    {
        $this->dataExtractor
            ->method('extractConfiguration')
            ->with($this->form)
            ->willReturn(['config' => 'foo']);

        $this->dataExtractor
            ->method('extractDefaultData')
            ->with($this->form)
            ->willReturn(['default_data' => 'foo']);

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $formData = [
            'config' => 'foo',
            'children' => [],
        ];

        $this->assertSame([
            'forms' => [
                'name' => $formData,
            ],
            'forms_by_hash' => [
                spl_object_hash($this->form) => $formData,
            ],
            'nb_errors' => 0,
        ], $this->dataCollector->getData());

        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $formData = [
            'config' => 'foo',
            'default_data' => 'foo',
            'children' => [],
        ];

        $this->assertSame([
            'forms' => [
                'name' => $formData,
            ],
            'forms_by_hash' => [
                spl_object_hash($this->form) => $formData,
            ],
            'nb_errors' => 0,
        ], $this->dataCollector->getData());
    }

    public function testBuildPreliminaryFormTreeWithoutCollectingAnyData()
    {
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $formData = [
            'children' => [],
        ];

        $this->assertSame([
            'forms' => [
                'name' => $formData,
            ],
            'forms_by_hash' => [
                spl_object_hash($this->form) => $formData,
            ],
            'nb_errors' => 0,
        ], $this->dataCollector->getData());
    }

    public function testBuildFinalFormTree()
    {
        $this->form->add($this->childForm);
        $this->view->children['child'] = $this->childView;

        $this->dataExtractor->expects($this->exactly(2))
            ->method('extractConfiguration')
            ->withConsecutive(
                [$this->form],
                [$this->childForm]
            )
            ->willReturnOnConsecutiveCalls(
                ['config' => 'foo'],
                ['config' => 'bar']
            );

        $this->dataExtractor->expects($this->exactly(2))
            ->method('extractDefaultData')
            ->withConsecutive(
                [$this->form],
                [$this->childForm]
            )
            ->willReturnOnConsecutiveCalls(
                ['default_data' => 'foo'],
                ['default_data' => 'bar']
            );

        $this->dataExtractor->expects($this->exactly(2))
            ->method('extractSubmittedData')
            ->withConsecutive(
                [$this->form],
                [$this->childForm]
            )
            ->willReturnOnConsecutiveCalls(
                ['submitted_data' => 'foo'],
                ['submitted_data' => 'bar']
            );

        $this->dataExtractor->expects($this->exactly(2))
            ->method('extractViewVariables')
            ->withConsecutive(
                [$this->view],
                [$this->childView]
            )
            ->willReturnOnConsecutiveCalls(
                ['view_vars' => 'foo'],
                ['view_vars' => 'bar']
            );

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->collectSubmittedData($this->form);
        $this->dataCollector->collectViewVariables($this->view);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = [
            'view_vars' => 'bar',
            'config' => 'bar',
            'default_data' => 'bar',
            'submitted_data' => 'bar',
            'children' => [],
        ];

        $formData = [
            'view_vars' => 'foo',
            'config' => 'foo',
            'default_data' => 'foo',
            'submitted_data' => 'foo',
            'has_children_error' => false,
            'children' => [
                'child' => $childFormData,
            ],
        ];

        $this->assertSame([
            'forms' => [
                'name' => $formData,
            ],
            'forms_by_hash' => [
                spl_object_hash($this->form) => $formData,
                spl_object_hash($this->childForm) => $childFormData,
            ],
            'nb_errors' => 0,
        ], $this->dataCollector->getData());
    }

    public function testSerializeWithFormAddedMultipleTimes()
    {
        $form1 = $this->createForm('form1');
        $form2 = $this->createForm('form2');
        $child1 = $this->createForm('child1');

        $form1View = new FormView();
        $form2View = new FormView();
        $child1View = new FormView();
        $child1View->vars['is_selected'] = function ($choice, array $values) {
            return \in_array($choice, $values, true);
        };

        $form1->add($child1);
        $form2->add($child1);

        $form1View->children['child1'] = $child1View;
        $form2View->children['child1'] = $child1View;

        $this->dataExtractor->expects($this->exactly(4))
            ->method('extractConfiguration')
            ->withConsecutive(
                [$form1],
                [$child1],
                [$form2],
                [$child1]
            )
            ->willReturnOnConsecutiveCalls(
                ['config' => 'foo'],
                ['config' => 'bar'],
                ['config' => 'foo'],
                ['config' => 'bar']
            );

        $this->dataExtractor->expects($this->exactly(4))
            ->method('extractDefaultData')
            ->withConsecutive(
                [$form1],
                [$child1],
                [$form2],
                [$child1]
            )
            ->willReturnOnConsecutiveCalls(
                ['default_data' => 'foo'],
                ['default_data' => 'bar'],
                ['default_data' => 'foo'],
                ['default_data' => 'bar']
            );

        $this->dataExtractor->expects($this->exactly(4))
            ->method('extractSubmittedData')
            ->withConsecutive(
                [$form1],
                [$child1],
                [$form2],
                [$child1]
            )
            ->willReturnOnConsecutiveCalls(
                ['submitted_data' => 'foo'],
                ['submitted_data' => 'bar'],
                ['submitted_data' => 'foo'],
                ['submitted_data' => 'bar']
            );

        $this->dataExtractor->expects($this->exactly(4))
            ->method('extractViewVariables')
            ->withConsecutive(
                [$form1View],
                [$child1View],
                [$form2View],
                [$child1View]
            )
            ->willReturnOnConsecutiveCalls(
                ['view_vars' => 'foo'],
                ['view_vars' => $child1View->vars],
                ['view_vars' => 'foo'],
                ['view_vars' => $child1View->vars]
            );

        $this->dataCollector->collectConfiguration($form1);
        $this->dataCollector->collectDefaultData($form1);
        $this->dataCollector->collectSubmittedData($form1);
        $this->dataCollector->collectViewVariables($form1View);
        $this->dataCollector->buildFinalFormTree($form1, $form1View);

        $this->dataCollector->collectConfiguration($form2);
        $this->dataCollector->collectDefaultData($form2);
        $this->dataCollector->collectSubmittedData($form2);
        $this->dataCollector->collectViewVariables($form2View);
        $this->dataCollector->buildFinalFormTree($form2, $form2View);

        $this->dataCollector->serialize();
    }

    public function testFinalFormReliesOnFormViewStructure()
    {
        $this->form->add($child1 = $this->createForm('first'));
        $this->form->add($child2 = $this->createForm('second'));

        $this->view->children['second'] = $this->childView;

        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $child1Data = [
            'children' => [],
        ];

        $child2Data = [
            'children' => [],
        ];

        $formData = [
            'children' => [
                'first' => $child1Data,
                'second' => $child2Data,
            ],
        ];

        $this->assertSame([
            'forms' => [
                'name' => $formData,
            ],
            'forms_by_hash' => [
                spl_object_hash($this->form) => $formData,
                spl_object_hash($child1) => $child1Data,
                spl_object_hash($child2) => $child2Data,
            ],
            'nb_errors' => 0,
        ], $this->dataCollector->getData());

        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $formData = [
            'children' => [
                // "first" not present in FormView
                'second' => $child2Data,
            ],
        ];

        $this->assertSame([
            'forms' => [
                'name' => $formData,
            ],
            'forms_by_hash' => [
                spl_object_hash($this->form) => $formData,
                spl_object_hash($child1) => $child1Data,
                spl_object_hash($child2) => $child2Data,
            ],
            'nb_errors' => 0,
        ], $this->dataCollector->getData());
    }

    public function testChildViewsCanBeWithoutCorrespondingChildForms()
    {
        // don't add $this->childForm to $this->form!

        $this->view->children['child'] = $this->childView;

        $this->dataExtractor->expects($this->exactly(2))
            ->method('extractConfiguration')
            ->withConsecutive(
                [$this->form],
                [$this->childForm]
            )
            ->willReturnOnConsecutiveCalls(
                ['config' => 'foo'],
                ['config' => 'bar']
            );

        // explicitly call collectConfiguration(), since $this->childForm is not
        // contained in the form tree
        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectConfiguration($this->childForm);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = [
            // no "config" key
            'children' => [],
        ];

        $formData = [
            'config' => 'foo',
            'children' => [
                'child' => $childFormData,
            ],
        ];

        $this->assertSame([
            'forms' => [
                'name' => $formData,
            ],
            'forms_by_hash' => [
                spl_object_hash($this->form) => $formData,
                // no child entry
            ],
            'nb_errors' => 0,
        ], $this->dataCollector->getData());
    }

    public function testChildViewsWithoutCorrespondingChildFormsMayBeExplicitlyAssociated()
    {
        // don't add $this->childForm to $this->form!

        $this->view->children['child'] = $this->childView;

        // but associate the two
        $this->dataCollector->associateFormWithView($this->childForm, $this->childView);

        $this->dataExtractor->expects($this->exactly(2))
            ->method('extractConfiguration')
            ->withConsecutive(
                [$this->form],
                [$this->childForm]
            )
            ->willReturnOnConsecutiveCalls(
                ['config' => 'foo'],
                ['config' => 'bar']
            );

        // explicitly call collectConfiguration(), since $this->childForm is not
        // contained in the form tree
        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectConfiguration($this->childForm);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = [
            'config' => 'bar',
            'children' => [],
        ];

        $formData = [
            'config' => 'foo',
            'children' => [
                'child' => $childFormData,
            ],
        ];

        $this->assertSame([
            'forms' => [
                'name' => $formData,
            ],
            'forms_by_hash' => [
                spl_object_hash($this->form) => $formData,
                spl_object_hash($this->childForm) => $childFormData,
            ],
            'nb_errors' => 0,
        ], $this->dataCollector->getData());
    }

    public function testCollectSubmittedDataCountsErrors()
    {
        $form1 = $this->createForm('form1');
        $childForm1 = $this->createForm('child1');
        $form2 = $this->createForm('form2');

        $form1->add($childForm1);
        $this->dataExtractor
             ->method('extractConfiguration')
             ->willReturn([]);
        $this->dataExtractor
             ->method('extractDefaultData')
             ->willReturn([]);
        $this->dataExtractor->expects($this->exactly(3))
            ->method('extractSubmittedData')
            ->withConsecutive(
                [$form1],
                [$childForm1],
                [$form2]
            )
            ->willReturnOnConsecutiveCalls(
                ['errors' => ['foo']],
                ['errors' => ['bar', 'bam']],
                ['errors' => ['baz']]
            );

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
            ->willReturn([]);
        $this->dataExtractor
            ->method('extractDefaultData')
            ->willReturn([]);
        $this->dataExtractor->expects($this->exactly(5))
            ->method('extractSubmittedData')
            ->withConsecutive(
                [$this->form],
                [$child1Form],
                [$child11Form],
                [$child2Form],
                [$child21Form]
            )
            ->willReturnOnConsecutiveCalls(
                ['errors' => []],
                ['errors' => []],
                ['errors' => ['foo']],
                ['errors' => []],
                ['errors' => []]
            );

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
        $this->assertArrayNotHasKey('has_children_error', $child11Data, 'The leaf data does not contains "has_children_error" property.');
        $this->assertFalse($child2Data['has_children_error']);
        $this->assertArrayNotHasKey('has_children_error', $child21Data, 'The leaf data does not contains "has_children_error" property.');
    }

    public function testReset()
    {
        $form = $this->createForm('my_form');

        $this->dataExtractor->expects($this->any())
            ->method('extractConfiguration')
            ->willReturn([]);
        $this->dataExtractor->expects($this->any())
            ->method('extractDefaultData')
            ->willReturn([]);
        $this->dataExtractor->expects($this->any())
            ->method('extractSubmittedData')
            ->with($form)
            ->willReturn(['errors' => ['baz']]);

        $this->dataCollector->buildPreliminaryFormTree($form);
        $this->dataCollector->collectSubmittedData($form);

        $this->dataCollector->reset();

        $this->assertSame(
            [
                'forms' => [],
                'forms_by_hash' => [],
                'nb_errors' => 0,
            ],
            $this->dataCollector->getData()
        );
    }

    public function testCollectMissingDataFromChildFormAddedOnFormEvents()
    {
        $form = $this->factory->createNamedBuilder('root', FormType::class, ['items' => null])
            ->add('items', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                // data is locked and modelData (null) is different to the
                // configured data, so modifications of the configured data
                // won't be allowed at this point. It also means *_SET_DATA
                // events won't dispatched either. Therefore, no child form
                // is created during the mapping of data to the form.
                'data' => ['foo'],
            ])
            ->getForm()
        ;
        $this->dataExtractor->expects($extractConfiguration = $this->exactly(4))
            ->method('extractConfiguration')
            ->willReturn([])
        ;
        $this->dataExtractor->expects($extractDefaultData = $this->exactly(4))
            ->method('extractDefaultData')
            ->willReturnCallback(static function (FormInterface $form) {
                // this simulate the call in extractDefaultData() method
                // where (if defaultDataSet is false) it fires *_SET_DATA
                // events, adding the form related to the configured data
                $form->getNormData();

                return [];
            })
        ;
        $this->dataExtractor->expects($this->exactly(4))
            ->method('extractSubmittedData')
            ->willReturn([])
        ;

        $this->dataCollector->collectConfiguration($form);
        $this->assertSame(2, $extractConfiguration->getInvocationCount(), 'only "root" and "items" forms were collected, the "items" children do not exist yet.');

        $this->dataCollector->collectDefaultData($form);
        $this->assertSame(3, $extractConfiguration->getInvocationCount(), 'extracted missing configuration of the "items" children ["0" => foo].');
        $this->assertSame(3, $extractDefaultData->getInvocationCount());
        $this->assertSame(['foo'], $form->get('items')->getData());

        $form->submit(['items' => ['foo', 'bar']]);
        $this->dataCollector->collectSubmittedData($form);
        $this->assertSame(4, $extractConfiguration->getInvocationCount(), 'extracted missing configuration of the "items" children ["1" => bar].');
        $this->assertSame(4, $extractDefaultData->getInvocationCount(), 'extracted missing default data of the "items" children ["1" => bar].');
        $this->assertSame(['foo', 'bar'], $form->get('items')->getData());
    }

    private function createForm($name)
    {
        $builder = new FormBuilder($name, null, $this->dispatcher, $this->factory);
        $builder->setCompound(true);
        $builder->setDataMapper($this->dataMapper);

        return $builder->getForm();
    }
}
