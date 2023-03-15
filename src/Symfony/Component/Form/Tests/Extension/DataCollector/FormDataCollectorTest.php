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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\Form\Extension\DataCollector\FormDataExtractor;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ResolvedFormTypeFactory;

class FormDataCollectorTest extends TestCase
{
    /**
     * @var FormDataCollector
     */
    private $dataCollector;

    /**
     * @var FormFactory
     */
    private $factory;

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

    protected function setUp(): void
    {
        $this->dataCollector = new FormDataCollector(new FormDataExtractor());
        $this->factory = new FormFactory(new FormRegistry([new CoreExtension()], new ResolvedFormTypeFactory()));
        $this->form = $this->createForm('name');
        $this->childForm = $this->createChildForm('child');
        $this->view = new FormView();
        $this->childView = new FormView();
    }

    public function testBuildPreliminaryFormTree()
    {
        $this->form->add($this->childForm);

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->collectSubmittedData($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $childFormData = [
            'id' => 'name_child',
            'name' => 'child',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $this->childForm->getConfig()->getOptions(),
            'default_data' => [
                'norm' => null,
                'view' => '',
            ],
            'submitted_data' => [
                'norm' => null,
                'view' => '',
            ],
            'errors' => [],
            'children' => [],
         ];

        $formData = [
            'id' => 'name',
            'name' => 'name',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $this->form->getConfig()->getOptions(),
            'default_data' => [
                'norm' => null,
            ],
            'submitted_data' => [
                'norm' => null,
            ],
            'errors' => [],
             'has_children_error' => false,
             'children' => [
                 'child' => $childFormData,
             ],
         ];

        $this->assertEquals([
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

        $this->dataCollector->collectConfiguration($form1);
        $this->dataCollector->collectConfiguration($form2);
        $this->dataCollector->buildPreliminaryFormTree($form1);

        $form1Data = [
            'id' => 'form1',
            'name' => 'form1',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $form1->getConfig()->getOptions(),
            'children' => [],
        ];

        $this->assertEquals([
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
            'id' => 'form2',
            'name' => 'form2',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $form2->getConfig()->getOptions(),
            'children' => [],
        ];

        $this->assertEquals([
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
        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $formData = [
            'id' => 'name',
            'name' => 'name',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $this->form->getConfig()->getOptions(),
            'children' => [],
        ];

        $this->assertEquals([
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
            'id' => 'name',
            'name' => 'name',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $this->form->getConfig()->getOptions(),
            'default_data' => [
                'norm' => null,
            ],
            'submitted_data' => [],
            'children' => [],
        ];

        $this->assertEquals([
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

        $this->assertEquals([
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

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->collectSubmittedData($this->form);
        $this->dataCollector->collectViewVariables($this->view);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = [
            'id' => 'name_child',
            'name' => 'child',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $this->childForm->getConfig()->getOptions(),
            'default_data' => [
                'norm' => null,
                'view' => '',
            ],
            'submitted_data' => [
                'norm' => null,
                'view' => '',
            ],
            'errors' => [],
            'view_vars' => [
                'attr' => [],
                'value' => null,
            ],
            'children' => [],
        ];

        $formData = [
            'id' => 'name',
            'name' => 'name',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $this->form->getConfig()->getOptions(),
            'default_data' => [
                'norm' => null,
            ],
            'submitted_data' => [
                'norm' => null,
            ],
            'errors' => [],
            'view_vars' => [
                'attr' => [],
                'value' => null,
            ],
            'has_children_error' => false,
            'children' => [
                'child' => $childFormData,
            ],
        ];

        $this->assertEquals([
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
        $this->expectNotToPerformAssertions();

        $form1 = $this->createForm('form1');
        $form2 = $this->createForm('form2');
        $child1 = $this->createChildForm('child1');

        $form1View = new FormView();
        $form2View = new FormView();
        $child1View = new FormView();
        $child1View->vars['is_selected'] = fn ($choice, array $values) => \in_array($choice, $values, true);

        $form1->add($child1);
        $form2->add($child1);

        $form1View->children['child1'] = $child1View;
        $form2View->children['child1'] = $child1View;

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

        serialize($this->dataCollector);
    }

    public function testFinalFormReliesOnFormViewStructure()
    {
        $this->form->add($child1 = $this->createChildForm('first'));
        $this->form->add($child2 = $this->createChildForm('second'));

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

        $this->assertEquals([
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

        $this->assertEquals([
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
            'id' => 'name',
            'name' => 'name',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $this->form->getConfig()->getOptions(),
            'children' => [
                'child' => $childFormData,
            ],
        ];

        $this->assertEquals([
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

        // explicitly call collectConfiguration(), since $this->childForm is not
        // contained in the form tree
        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectConfiguration($this->childForm);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = [
            'id' => 'child',
            'name' => 'child',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $this->childForm->getConfig()->getOptions(),
            'children' => [],
        ];

        $formData = [
            'id' => 'name',
            'name' => 'name',
            'type_class' => FormType::class,
            'synchronized' => true,
            'passed_options' => [],
            'resolved_options' => $this->form->getConfig()->getOptions(),
            'children' => [
                'child' => $childFormData,
            ],
        ];

        $this->assertEquals([
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
        $childForm1 = $this->createChildForm('child1');
        $form2 = $this->createForm('form2');

        $form1->add($childForm1);

        $form1->addError(new FormError('foo'));
        $childForm1->addError(new FormError('bar'));
        $childForm1->addError(new FormError('bam'));
        $form2->addError(new FormError('baz'));

        $this->dataCollector->collectSubmittedData($form1);

        $data = $this->dataCollector->getData();
        $this->assertSame(3, $data['nb_errors']);

        $this->dataCollector->collectSubmittedData($form2);

        $data = $this->dataCollector->getData();
        $this->assertSame(4, $data['nb_errors']);
    }

    public function testCollectSubmittedDataExpandedFormsErrors()
    {
        $child1Form = $this->createChildForm('child1', true);
        $child11Form = $this->createChildForm('child11');
        $child2Form = $this->createChildForm('child2', true);
        $child21Form = $this->createChildForm('child21');

        $child1Form->add($child11Form);
        $child2Form->add($child21Form);
        $this->form->add($child1Form);
        $this->form->add($child2Form);

        $child11Form->addError(new FormError('foo'));

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

        $this->dataCollector->buildPreliminaryFormTree($form);
        $this->dataCollector->collectSubmittedData($form);

        $this->assertGreaterThan(0, \count($this->dataCollector->getData()['forms']));
        $this->assertGreaterThan(0, \count($this->dataCollector->getData()['forms_by_hash']));

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

        $this->dataCollector->collectConfiguration($form);
        $this->dataCollector->buildPreliminaryFormTree($form);
        $data = $this->dataCollector->getData();
        $this->assertCount(2, $data['forms_by_hash'], 'only "root" and "items" forms were collected, the "items" children do not exist yet.');

        foreach ($data['forms_by_hash'] as $formData) {
            $this->assertArrayNotHasKey('default_data', $formData);
        }

        $this->dataCollector->collectDefaultData($form);
        $this->dataCollector->buildPreliminaryFormTree($form);
        $data = $this->dataCollector->getData();
        $this->assertCount(3, $data['forms_by_hash'], 'extracted missing configuration of the "items" children ["0" => foo].');
        $this->assertSame(['foo'], $form->get('items')->getData());

        foreach ($data['forms_by_hash'] as $formData) {
            $this->assertArrayHasKey('default_data', $formData);
        }

        $form->submit(['items' => ['foo', 'bar']]);
        $this->dataCollector->collectSubmittedData($form);
        $this->dataCollector->buildPreliminaryFormTree($form);
        $data = $this->dataCollector->getData();
        $this->assertCount(4, $data['forms_by_hash'], 'extracted missing configuration of the "items" children ["1" => bar].');
        $this->assertSame(['foo', 'bar'], $form->get('items')->getData());

        foreach ($data['forms_by_hash'] as $formData) {
            $this->assertArrayHasKey('default_data', $formData);
        }
    }

    private function createForm(string $name): FormInterface
    {
        return $this->factory->createNamedBuilder($name)->getForm();
    }

    private function createChildForm(string $name, bool $compound = false): FormInterface
    {
        return $this->factory->createNamedBuilder($name, FormType::class, null, ['auto_initialize' => false, 'compound' => $compound])->getForm();
    }
}
