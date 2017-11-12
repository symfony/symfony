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

/**
 * Abstract class providing test cases for the Bootstrap 4 horizontal Twig form theme.
 *
 * @author Hidde Wieringa <hidde@hiddewieringa.nl>
 */
abstract class AbstractBootstrap4HorizontalLayoutTest extends AbstractBootstrap4LayoutTest
{
    public function testLabelOnForm(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType');
        $view = $form->createView();
        $this->renderWidget($view, array('label' => 'foo'));
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/legend
    [@class="col-form-label col-sm-2 col-form-legend required"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelDoesNotRenderFieldAttributes(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), null, array(
            'attr' => array(
                'class' => 'my&class',
            ),
        ));

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="col-form-label col-sm-2 form-control-label required"]
'
        );
    }

    public function testLabelWithCustomAttributesPassedDirectly(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), null, array(
            'label_attr' => array(
                'class' => 'my&class',
            ),
        ));

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class col-form-label col-sm-2 form-control-label required"]
'
        );
    }

    public function testLabelWithCustomTextAndCustomAttributesPassedDirectly(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), 'Custom label', array(
            'label_attr' => array(
                'class' => 'my&class',
            ),
        ));

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class col-form-label col-sm-2 form-control-label required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextAsOptionAndCustomAttributesPassedDirectly(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array(
            'label' => 'Custom label',
        ));
        $html = $this->renderLabel($form->createView(), null, array(
            'label_attr' => array(
                'class' => 'my&class',
            ),
        ));

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class col-form-label col-sm-2 form-control-label required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLegendOnExpandedType(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'label' => 'Custom label',
            'expanded' => true,
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
        ));
        $view = $form->createView();
        $this->renderWidget($view);
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/legend
    [@class="col-sm-2 col-form-legend required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testStartTag(): void
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'get',
            'action' => 'http://example.com/directory',
        ));

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory">', $html);
    }

    public function testStartTagWithOverriddenVars(): void
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'put',
            'action' => 'http://example.com/directory',
        ));

        $html = $this->renderStart($form->createView(), array(
            'method' => 'post',
            'action' => 'http://foo.com/directory',
        ));

        $this->assertSame('<form name="form" method="post" action="http://foo.com/directory">', $html);
    }

    public function testStartTagForMultipartForm(): void
    {
        $form = $this->factory->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
                'method' => 'get',
                'action' => 'http://example.com/directory',
            ))
            ->add('file', 'Symfony\Component\Form\Extension\Core\Type\FileType')
            ->getForm();

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" enctype="multipart/form-data">', $html);
    }

    public function testStartTagWithExtraAttributes(): void
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'get',
            'action' => 'http://example.com/directory',
        ));

        $html = $this->renderStart($form->createView(), array(
            'attr' => array('class' => 'foobar'),
        ));

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" class="foobar">', $html);
    }

    public function testCheckboxRow(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType');
        $view = $form->createView();
        $html = $this->renderRow($view, array('label' => 'foo'));

        $this->assertMatchesXpath($html, '/div[@class="form-group row"]/div[@class="col-sm-2" or @class="col-sm-10"]', 2);
    }
}
