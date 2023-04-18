<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;

/**
 * Abstract class providing test cases for the Bootstrap 5 horizontal Twig form theme.
 *
 * @author Romain Monteil <monteil.romain@gmail.com>
 */
abstract class AbstractBootstrap5HorizontalLayoutTestCase extends AbstractBootstrap5LayoutTestCase
{
    public function testRow()
    {
        $form = $this->factory->createNamed('')->add('name', TextType::class);
        $form->get('name')->addError(new FormError('[trans]Error![/trans]'));
        $html = $this->renderRow($form->get('name')->createView());

        $this->assertMatchesXpath($html,
            '/div
    [@class="mb-3 row"]
    [
        ./label
            [@for="name"]
            [@class="col-form-label col-sm-2 required"]
        /following-sibling::div
            [@class="col-sm-10"]
            [
                ./input[@id="name"]
                /following-sibling::div
                    [@class="invalid-feedback d-block"]
                    [.="[trans]Error![/trans]"]
            ]
            [count(./div)=1]
    ]
'
        );
    }

    public function testRowWithCustomClass()
    {
        $form = $this->factory->createNamed('')->add('name', TextType::class);
        $form->get('name')->addError(new FormError('[trans]Error![/trans]'));
        $html = $this->renderRow($form->get('name')->createView(), [
            'row_attr' => [
                'class' => 'mb-5',
            ],
        ]);

        $this->assertMatchesXpath($html,
            '/div
    [@class="mb-5 row"]
    [
        ./label
            [@for="name"]
            [@class="col-form-label col-sm-2 required"]
        /following-sibling::div
            [@class="col-sm-10"]
            [
                ./input[@id="name"]
                /following-sibling::div
                    [@class="invalid-feedback d-block"]
                    [.="[trans]Error![/trans]"]
            ]
            [count(./div)=1]
    ]
'
        );
    }

    public function testLabelOnForm()
    {
        $form = $this->factory->createNamed('name', DateType::class, null, ['widget' => 'choice']);
        $view = $form->createView();
        $this->renderWidget($view, ['label' => 'foo']);
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
            '/legend
    [@class="col-form-label col-sm-2 required"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelDoesNotRenderFieldAttributes()
    {
        $form = $this->factory->createNamed('name', TextType::class);
        $html = $this->renderLabel($form->createView(), null, [
            'attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
            '/label
    [@for="name"]
    [@class="col-form-label col-sm-2 required"]
'
        );
    }

    public function testLabelWithCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', TextType::class);
        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
            '/label
    [@for="name"]
    [@class="my&class col-form-label col-sm-2 required"]
'
        );
    }

    public function testLabelWithCustomTextAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', TextType::class);
        $html = $this->renderLabel($form->createView(), 'Custom label', [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
            '/label
    [@for="name"]
    [@class="my&class col-form-label col-sm-2 required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextAsOptionAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'label' => 'Custom label',
        ]);
        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
            '/label
    [@for="name"]
    [@class="my&class col-form-label col-sm-2 required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelHtmlDefaultIsFalse()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'label' => '<b>Bolded label</b>',
        ]);

        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class col-form-label col-sm-2 required"][.="[trans]<b>Bolded label</b>[/trans]"]');
        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class col-form-label col-sm-2 required"]/b[.="Bolded label"]', 0);
    }

    public function testLabelHtmlIsTrue()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'label' => '<b>Bolded label</b>',
            'label_html' => true,
        ]);

        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class col-form-label col-sm-2 required"][.="[trans]<b>Bolded label</b>[/trans]"]', 0);
        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class col-form-label col-sm-2 required"]/b[.="Bolded label"]');
    }

    public function testLegendOnExpandedType()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, null, [
            'label' => 'Custom label',
            'expanded' => true,
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
        ]);
        $view = $form->createView();
        $this->renderWidget($view);
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
            '/legend
    [@class="col-sm-2 col-form-label required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testCheckboxRow()
    {
        $form = $this->factory->createNamed('name', CheckboxType::class);
        $view = $form->createView();
        $html = $this->renderRow($view, ['label' => 'foo']);

        $this->assertMatchesXpath($html, '/div[@class="mb-3 row"]/div[@class="col-sm-2" or @class="col-sm-10"]', 2);
    }

    public function testCheckboxRowWithHelp()
    {
        $form = $this->factory->createNamed('name', CheckboxType::class);
        $view = $form->createView();
        $html = $this->renderRow($view, ['label' => 'foo', 'help' => 'really helpful text']);

        $this->assertMatchesXpath($html,
            '/div
    [@class="mb-3 row"]
    [
        ./div[@class="col-sm-2" or @class="col-sm-10"]
        /following-sibling::div[@class="col-sm-2" or @class="col-sm-10"]
        [
            ./div
                [@class="form-text mb-0 help-text"]
                [.="[trans]really helpful text[/trans]"]
        ]
    ]
'
        );
    }

    public function testRadioRowWithHelp()
    {
        $form = $this->factory->createNamed('name', RadioType::class, false);
        $html = $this->renderRow($form->createView(), ['label' => 'foo', 'help' => 'really helpful text']);

        $this->assertMatchesXpath($html,
            '/div
    [@class="mb-3 row"]
    [
        ./div[@class="col-sm-2" or @class="col-sm-10"]
        /following-sibling::div[@class="col-sm-2" or @class="col-sm-10"]
        [
            ./div
                [@class="form-text mb-0 help-text"]
                [.="[trans]really helpful text[/trans]"]
        ]
    ]
'
        );
    }

    public function testFileWithGroup()
    {
        $form = $this->factory->createNamed('name', FileType::class);
        $html = $this->renderRow($form->createView(), [
            'id' => 'n/a',
            'attr' => [
                'class' => 'my&class',
            ],
            'row_attr' => [
                'class' => 'input-group mb-3',
            ],
        ]);

        $this->assertMatchesXpath($html,
            '/div
    [@class="mb-3 row"]
    [
        ./div
            [@class="col-sm-2"]
        /following-sibling::div
            [@class="col-sm-10"]
            [
                ./div
                    [@class="input-group"]
                    [
                        ./label
                            [@class="input-group-text required"]
                            [.="[trans]Name[/trans]"]
                        /following-sibling::input
                            [@type="file"]
                            [@name="name"]
                            [@class="my&class form-control"]
                    ]
            ]
    ]
'
        );
    }

    public function testFloatingLabel()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'attr' => [
                'placeholder' => 'name',
            ],
            'row_attr' => [
                'class' => 'form-floating mb-3',
            ],
        ]);

        $html = $this->renderRow($form->createView());

        $this->assertMatchesXpath($html,
            '/div
    [@class="mb-3 row"]
    [
        ./div
            [@class="col-sm-2"]
        /following-sibling::div
            [@class="col-sm-10"]
            [
                ./div
                    [@class="form-floating"]
                    [
                        ./input
                            [@id="name"]
                            [@placeholder="[trans]name[/trans]"]
                        /following-sibling::label
                            [@for="name"]
                    ]
            ]
    ]
'
        );
    }
}
