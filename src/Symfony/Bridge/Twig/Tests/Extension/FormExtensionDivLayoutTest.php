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
