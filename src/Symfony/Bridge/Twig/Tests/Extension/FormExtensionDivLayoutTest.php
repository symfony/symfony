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
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class FormExtensionDivLayoutTest extends AbstractDivLayoutTestCase
{
    public function testThemeBlockInheritanceUsingUse()
    {
        $view = $this->factory
            ->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\EmailType')
            ->createView()
        ;

        $this->setTheme($view, ['theme_use.html.twig']);

        $this->assertMatchesXpath(
            $this->renderWidget($view),
            '/input[@type="email"][@rel="theme"]'
        );
    }

    public function testThemeBlockInheritanceUsingExtend()
    {
        $view = $this->factory
            ->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\EmailType')
            ->createView()
        ;

        $this->setTheme($view, ['theme_extends.html.twig']);

        $this->assertMatchesXpath(
            $this->renderWidget($view),
            '/input[@type="email"][@rel="theme"]'
        );
    }

    public function testThemeBlockInheritanceUsingDynamicExtend()
    {
        $view = $this->factory
            ->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\EmailType')
            ->createView()
        ;

        $this->renderer->setTheme($view, ['page_dynamic_extends.html.twig']);
        $this->assertMatchesXpath(
            $this->renderer->searchAndRenderBlock($view, 'row'),
            '/div/label[text()="child"]'
        );
    }

    public static function isSelectedChoiceProvider(): array
    {
        return [
            [true, '0', '0'],
            [true, '1', '1'],
            [true, '', ''],
            [true, '1.23', '1.23'],
            [true, 'foo', 'foo'],
            [true, 'foo10', 'foo10'],
            [true, 'foo', [1, 'foo', 'foo10']],

            [false, 10, [1, 'foo', 'foo10']],
            [false, 0, [1, 'foo', 'foo10']],
        ];
    }

    /**
     * @dataProvider isSelectedChoiceProvider
     */
    public function testIsChoiceSelected($expected, $choice, $value)
    {
        $choice = new ChoiceView($choice, $choice, $choice.' label');

        $this->assertSame($expected, \Symfony\Bridge\Twig\Extension\twig_is_selected_choice($choice, $value));
    }

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

    public static function isRootFormProvider(): array
    {
        return [
            [true, new FormView()],
            [false, new FormView(new FormView())],
        ];
    }

    /**
     * @dataProvider isRootFormProvider
     */
    public function testIsRootForm($expected, FormView $formView)
    {
        $this->assertSame($expected, \Symfony\Bridge\Twig\Extension\twig_is_root_form($formView));
    }

    public function testMoneyWidgetInIso()
    {
        $environment = new Environment(new FilesystemLoader([
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ]), ['strict_variables' => true]);
        $environment->addExtension(new TranslationExtension(new StubTranslator()));
        $environment->addExtension(new FormExtension());
        $environment->setCharset('ISO-8859-1');

        $rendererEngine = new TwigRendererEngine([
            'form_div_layout.html.twig',
            'custom_widgets.html.twig',
        ], $environment);
        $this->renderer = new FormRenderer($rendererEngine, $this->createMock(CsrfTokenManagerInterface::class));
        $this->registerTwigRuntimeLoader($environment, $this->renderer);

        $view = $this->factory
            ->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\MoneyType')
            ->createView()
        ;

        $this->assertSame('&euro; <input type="text" id="name" name="name" required="required" />', $this->renderWidget($view));
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

    protected function renderForm(FormView $view, array $vars = []): string
    {
        return $this->renderer->renderBlock($view, 'form', $vars);
    }

    protected function renderLabel(FormView $view, $label = null, array $vars = []): string
    {
        if (null !== $label) {
            $vars += ['label' => $label];
        }

        return $this->renderer->searchAndRenderBlock($view, 'label', $vars);
    }

    protected function renderHelp(FormView $view): string
    {
        return $this->renderer->searchAndRenderBlock($view, 'help');
    }

    protected function renderErrors(FormView $view): string
    {
        return $this->renderer->searchAndRenderBlock($view, 'errors');
    }

    protected function renderWidget(FormView $view, array $vars = []): string
    {
        return $this->renderer->searchAndRenderBlock($view, 'widget', $vars);
    }

    protected function renderRow(FormView $view, array $vars = []): string
    {
        return $this->renderer->searchAndRenderBlock($view, 'row', $vars);
    }

    protected function renderRest(FormView $view, array $vars = []): string
    {
        return $this->renderer->searchAndRenderBlock($view, 'rest', $vars);
    }

    protected function renderStart(FormView $view, array $vars = []): string
    {
        return $this->renderer->renderBlock($view, 'form_start', $vars);
    }

    protected function renderEnd(FormView $view, array $vars = []): string
    {
        return $this->renderer->renderBlock($view, 'form_end', $vars);
    }

    protected function setTheme(FormView $view, array $themes, $useDefaultThemes = true): void
    {
        $this->renderer->setTheme($view, $themes, $useDefaultThemes);
    }

    public static function themeBlockInheritanceProvider(): array
    {
        return [
            [['theme.html.twig']],
        ];
    }

    public static function themeInheritanceProvider(): array
    {
        return [
            [['parent_label.html.twig'], ['child_label.html.twig']],
        ];
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
            // the value can be any template that exists
            'dynamic_template_name' => 'child_label',
        ];
    }

    protected function getThemes(): array
    {
        return [
            'form_div_layout.html.twig',
            'custom_widgets.html.twig',
        ];
    }
}
