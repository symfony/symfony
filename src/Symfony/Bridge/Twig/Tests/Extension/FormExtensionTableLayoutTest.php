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
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Tests\AbstractTableLayoutTest;
use Twig\Environment;

class FormExtensionTableLayoutTest extends AbstractTableLayoutTest
{
    use RuntimeLoaderProvider;

    /**
     * @var FormRenderer
     */
    private $renderer;

    protected static $supportedFeatureSetVersion = 304;

    /**
     * @before
     */
    public function doSetUp()
    {
        $loader = new StubFilesystemLoader([
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ]);

        $environment = new Environment($loader, ['strict_variables' => true]);
        $environment->addExtension(new TranslationExtension(new StubTranslator()));
        $environment->addGlobal('global', '');
        $environment->addExtension(new FormExtension());

        $rendererEngine = new TwigRendererEngine([
            'form_table_layout.html.twig',
            'custom_widgets.html.twig',
        ], $environment);
        $this->renderer = new FormRenderer($rendererEngine, $this->getMockBuilder('Symfony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock());
        $this->registerTwigRuntimeLoader($environment, $this->renderer);
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
}
