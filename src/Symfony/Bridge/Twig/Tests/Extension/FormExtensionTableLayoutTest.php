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

use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;

class FormExtensionTableLayoutTest extends AbstractTableLayoutTestCase
{
    public function testStartTagHasNoActionAttributeWhenActionIsEmpty()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
            'method' => 'get',
            'action' => '',
        ]);

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get">', $html);
    }

    public function testStartTagHasActionAttributeWhenActionIsZero()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
            'method' => 'get',
            'action' => '0',
        ]);

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="0">', $html);
    }

    public function testHelpAttr()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'Help text test!',
            'help_attr' => [
                'class' => 'class-test',
            ],
        ]);
        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="class-test help-text"]
    [.="[trans]Help text test![/trans]"]
'
        );
    }

    public function testHelpHtmlDefaultIsFalse()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'Help <b>text</b> test!',
        ]);

        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="help-text"]
    [.="[trans]Help <b>text</b> test![/trans]"]
'
        );

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="help-text"]
    /b
    [.="text"]
', 0
        );
    }

    public function testHelpHtmlIsFalse()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'Help <b>text</b> test!',
            'help_html' => false,
        ]);

        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="help-text"]
    [.="[trans]Help <b>text</b> test![/trans]"]
'
        );

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="help-text"]
    /b
    [.="text"]
', 0
        );
    }

    public function testHelpHtmlIsTrue()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'Help <b>text</b> test!',
            'help_html' => true,
        ]);

        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="help-text"]
    [.="[trans]Help <b>text</b> test![/trans]"]
', 0
        );

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="help-text"]
    /b
    [.="text"]
'
        );
    }

    public function testLabelHtmlDefaultIsFalse()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'label' => '<b>Bolded label</b>',
        ]);

        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class required"][.="[trans]<b>Bolded label</b>[/trans]"]');
        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class required"]/b[.="Bolded label"]', 0);
    }

    public function testLabelHtmlIsTrue()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'label' => '<b>Bolded label</b>',
            'label_html' => true,
        ]);

        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class required"][.="[trans]<b>Bolded label</b>[/trans]"]', 0);
        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class required"]/b[.="Bolded label"]');
    }

    protected function getTemplatePaths(): array
    {
        return [
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ];
    }

    protected function getTwigExtensions(): array
    {
        return [
            new TranslationExtension(new StubTranslator()),
            new FormExtension(),
        ];
    }

    protected function getTwigGlobals(): array
    {
        return [
            'global' => '',
        ];
    }

    protected function getThemes(): array
    {
        return [
            'form_table_layout.html.twig',
            'custom_widgets.html.twig',
        ];
    }
}
