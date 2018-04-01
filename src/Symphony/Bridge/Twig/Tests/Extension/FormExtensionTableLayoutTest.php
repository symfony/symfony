<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Tests\Extension;

use Symphony\Component\Form\FormRenderer;
use Symphony\Component\Form\FormView;
use Symphony\Bridge\Twig\Form\TwigRendererEngine;
use Symphony\Bridge\Twig\Extension\FormExtension;
use Symphony\Bridge\Twig\Extension\TranslationExtension;
use Symphony\Component\Form\Tests\AbstractTableLayoutTest;
use Symphony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;
use Symphony\Bridge\Twig\Tests\Extension\Fixtures\StubFilesystemLoader;
use Twig\Environment;

class FormExtensionTableLayoutTest extends AbstractTableLayoutTest
{
    use RuntimeLoaderProvider;

    /**
     * @var FormRenderer
     */
    private $renderer;

    protected function setUp()
    {
        parent::setUp();

        $loader = new StubFilesystemLoader(array(
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ));

        $environment = new Environment($loader, array('strict_variables' => true));
        $environment->addExtension(new TranslationExtension(new StubTranslator()));
        $environment->addGlobal('global', '');
        $environment->addExtension(new FormExtension());

        $rendererEngine = new TwigRendererEngine(array(
            'form_table_layout.html.twig',
            'custom_widgets.html.twig',
        ), $environment);
        $this->renderer = new FormRenderer($rendererEngine, $this->getMockBuilder('Symphony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock());
        $this->registerTwigRuntimeLoader($environment, $this->renderer);
    }

    public function testStartTagHasNoActionAttributeWhenActionIsEmpty()
    {
        $form = $this->factory->create('Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'get',
            'action' => '',
        ));

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get">', $html);
    }

    public function testStartTagHasActionAttributeWhenActionIsZero()
    {
        $form = $this->factory->create('Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'get',
            'action' => '0',
        ));

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="0">', $html);
    }

    protected function renderForm(FormView $view, array $vars = array())
    {
        return (string) $this->renderer->renderBlock($view, 'form', $vars);
    }

    protected function renderLabel(FormView $view, $label = null, array $vars = array())
    {
        if (null !== $label) {
            $vars += array('label' => $label);
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

    protected function renderWidget(FormView $view, array $vars = array())
    {
        return (string) $this->renderer->searchAndRenderBlock($view, 'widget', $vars);
    }

    protected function renderRow(FormView $view, array $vars = array())
    {
        return (string) $this->renderer->searchAndRenderBlock($view, 'row', $vars);
    }

    protected function renderRest(FormView $view, array $vars = array())
    {
        return (string) $this->renderer->searchAndRenderBlock($view, 'rest', $vars);
    }

    protected function renderStart(FormView $view, array $vars = array())
    {
        return (string) $this->renderer->renderBlock($view, 'form_start', $vars);
    }

    protected function renderEnd(FormView $view, array $vars = array())
    {
        return (string) $this->renderer->renderBlock($view, 'form_end', $vars);
    }

    protected function setTheme(FormView $view, array $themes, $useDefaultThemes = true)
    {
        $this->renderer->setTheme($view, $themes, $useDefaultThemes);
    }
}
