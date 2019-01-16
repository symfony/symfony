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

abstract class AbstractBootstrap3HorizontalLayoutTest extends AbstractBootstrap3LayoutTest
{
    public function testLabelOnForm()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType');
        $view = $form->createView();
        $this->renderWidget($view, ['label' => 'foo']);
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/label
    [@class="col-sm-2 control-label required"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelDoesNotRenderFieldAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), null, [
            'attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="col-sm-2 control-label required"]
'
        );
    }

    public function testLabelWithCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class col-sm-2 control-label required"]
'
        );
    }

    public function testLabelWithCustomTextAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), 'Custom label', [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class col-sm-2 control-label required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextAsOptionAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
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
    [@class="my&class col-sm-2 control-label required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testStartTag()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
            'method' => 'get',
            'action' => 'http://example.com/directory',
        ]);

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" class="form-horizontal">', $html);
    }

    public function testStartTagWithOverriddenVars()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
            'method' => 'put',
            'action' => 'http://example.com/directory',
        ]);

        $html = $this->renderStart($form->createView(), [
            'method' => 'post',
            'action' => 'http://foo.com/directory',
        ]);

        $this->assertSame('<form name="form" method="post" action="http://foo.com/directory" class="form-horizontal">', $html);
    }

    public function testStartTagForMultipartForm()
    {
        $form = $this->factory->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'method' => 'get',
                'action' => 'http://example.com/directory',
            ])
            ->add('file', 'Symfony\Component\Form\Extension\Core\Type\FileType')
            ->getForm();

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" class="form-horizontal" enctype="multipart/form-data">', $html);
    }

    public function testStartTagWithExtraAttributes()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
            'method' => 'get',
            'action' => 'http://example.com/directory',
        ]);

        $html = $this->renderStart($form->createView(), [
            'attr' => ['class' => 'foobar'],
        ]);

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" class="foobar form-horizontal">', $html);
    }

    public function testCheckboxRow()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType');
        $view = $form->createView();
        $html = $this->renderRow($view, ['label' => 'foo']);

        $this->assertMatchesXpath($html, '/div[@class="form-group"]/div[@class="col-sm-2" or @class="col-sm-10"]', 2);
    }
}
