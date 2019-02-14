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
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubFilesystemLoader;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Tests\AbstractDivLayoutTest;
use Twig\Environment;

class FormExtensionDivLayoutTest extends AbstractDivLayoutTest
{
    use RuntimeLoaderProvider;

    /**
     * @var FormRenderer
     */
    private $renderer;

    protected static $supportedFeatureSetVersion = 304;

    protected function setUp()
    {
        parent::setUp();

        $loader = new StubFilesystemLoader([
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ]);

        $environment = new Environment($loader, ['strict_variables' => true]);
        $environment->addExtension(new TranslationExtension(new StubTranslator()));
        $environment->addGlobal('global', '');
        // the value can be any template that exists
        $environment->addGlobal('dynamic_template_name', 'child_label');
        $environment->addExtension(new FormExtension());

        $rendererEngine = new TwigRendererEngine([
            'form_div_layout.html.twig',
            'custom_widgets.html.twig',
        ], $environment);
        $this->renderer = new FormRenderer($rendererEngine, $this->getMockBuilder('Symfony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock());
        $this->registerTwigRuntimeLoader($environment, $this->renderer);
    }

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

    public function isSelectedChoiceProvider()
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

    public function isRootFormProvider()
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
        $environment = new Environment(new StubFilesystemLoader([
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
        $this->renderer = new FormRenderer($rendererEngine, $this->getMockBuilder('Symfony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock());
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
            '/p
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
            '/p
    [@id="name_help"]
    [@class="help-text"]
    [.="[trans]Help <b>text</b> test![/trans]"]
'
        );

        $this->assertMatchesXpath($html,
            '/p
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
            '/p
    [@id="name_help"]
    [@class="help-text"]
    [.="[trans]Help <b>text</b> test![/trans]"]
'
        );

        $this->assertMatchesXpath($html,
            '/p
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
            '/p
    [@id="name_help"]
    [@class="help-text"]
    [.="[trans]Help <b>text</b> test![/trans]"]
', 0
        );

        $this->assertMatchesXpath($html,
            '/p
    [@id="name_help"]
    [@class="help-text"]
    /b
    [.="text"]
'
        );
    }

    public function testLabelWithTranslationParameters()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), 'Address is %address%', [
            'label_translation_parameters' => [
                '%address%' => 'Paris, rue de la Paix',
            ],
        ]);

        $this->assertMatchesXpath($html,
            '/label
    [@for="name"]
    [.="[trans]Address is Paris, rue de la Paix[/trans]"]
'
        );
    }

    public function testHelpWithTranslationParameters()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'for company %company%',
            'help_translation_parameters' => [
                '%company%' => 'ACME Ltd.',
            ],
        ]);
        $html = $this->renderHelp($form->createView());

        $this->assertMatchesXpath($html,
            '/*
    [@id="name_help"]
    [.="[trans]for company ACME Ltd.[/trans]"]
'
        );
    }

    public function testAttributesWithTranslationParameters()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'attr' => [
                'title' => 'Message to %company%',
                'placeholder' => 'Enter a message to %company%',
            ],
            'attr_translation_parameters' => [
                '%company%' => 'ACME Ltd.',
            ],
        ]);
        $html = $this->renderWidget($form->createView());

        $this->assertMatchesXpath($html,
            '/input
    [@title="[trans]Message to ACME Ltd.[/trans]"]
    [@placeholder="[trans]Enter a message to ACME Ltd.[/trans]"]
'
        );
    }

    public function testButtonWithTranslationParameters()
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('mybutton', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', [
                'label' => 'Submit to %company%',
                'label_translation_parameters' => [
                    '%company%' => 'ACME Ltd.',
                ],
            ])
            ->getForm();
        $view = $form->get('mybutton')->createView();
        $html = $this->renderWidget($view, ['label_format' => 'form.%name%']);

        $this->assertMatchesXpath($html,
            '/button
    [.="[trans]Submit to ACME Ltd.[/trans]"]
'
        );
    }

    protected function renderForm(FormView $view, array $vars = [])
    {
        return (string) $this->renderer->renderBlock($view, 'form', $vars);
    }

    protected function renderLabel(FormView $view, $label = null, array $vars = [])
    {
        if (null !== $label) {
            $vars += ['label' => $label];
        }

        return (string) $this->renderer->searchAndRenderBlock($view, 'label', $vars);
    }

    protected function renderHelp(FormView $view)
    {
        return (string) $this->renderer->searchAndRenderBlock($view, 'help');
    }

    protected function renderErrors(FormView $view)
    {
        return (string) $this->renderer->searchAndRenderBlock($view, 'errors');
    }

    protected function renderWidget(FormView $view, array $vars = [])
    {
        return (string) $this->renderer->searchAndRenderBlock($view, 'widget', $vars);
    }

    protected function renderRow(FormView $view, array $vars = [])
    {
        return (string) $this->renderer->searchAndRenderBlock($view, 'row', $vars);
    }

    protected function renderRest(FormView $view, array $vars = [])
    {
        return (string) $this->renderer->searchAndRenderBlock($view, 'rest', $vars);
    }

    protected function renderStart(FormView $view, array $vars = [])
    {
        return (string) $this->renderer->renderBlock($view, 'form_start', $vars);
    }

    protected function renderEnd(FormView $view, array $vars = [])
    {
        return (string) $this->renderer->renderBlock($view, 'form_end', $vars);
    }

    protected function setTheme(FormView $view, array $themes, $useDefaultThemes = true)
    {
        $this->renderer->setTheme($view, $themes, $useDefaultThemes);
    }

    public static function themeBlockInheritanceProvider()
    {
        return [
            [['theme.html.twig']],
        ];
    }

    public static function themeInheritanceProvider()
    {
        return [
            [['parent_label.html.twig'], ['child_label.html.twig']],
        ];
    }
}
