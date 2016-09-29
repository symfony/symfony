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
use Symfony\Bridge\Twig\Form\TwigRenderer;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubFilesystemLoader;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Tests\AbstractBootstrap3LayoutTest;

class FormExtensionBootstrap3LayoutTest extends AbstractBootstrap3LayoutTest
{
    protected $testableFeatures = array(
        'choice_attr',
    );

    private $environment;

    protected function setUp()
    {
        parent::setUp();

        $loader = new StubFilesystemLoader(array(
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ));

        $this->environment = new \Twig_Environment($loader, array('strict_variables' => true));
        $this->environment->addExtension(new TranslationExtension(new StubTranslator()));
        $this->environment->addExtension(new FormExtension());

        $rendererEngine = new TwigRendererEngine(array(
            'bootstrap_3_layout.html.twig',
            'custom_widgets.html.twig',
        ), $this->environment);
        $renderer = new TwigRenderer($rendererEngine, $this->getMock('Symfony\Component\Security\Csrf\CsrfTokenManagerInterface'));

        $loader = $this->getMock('Twig_RuntimeLoaderInterface');
        $loader->expects($this->any())->method('load')->will($this->returnValueMap(array(
            array('form', $renderer),
            array('translator', null),
        )));

        $this->environment->registerRuntimeLoader($loader);
    }

    public function testStartTagHasNoActionAttributeWhenActionIsEmpty()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'get',
            'action' => '',
        ));

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get">', $html);
    }

    public function testStartTagHasActionAttributeWhenActionIsZero()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'get',
            'action' => '0',
        ));

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="0">', $html);
    }

    protected function renderForm(FormView $view, array $vars = array())
    {
        return (string) $this->environment->getRuntime('form')->renderBlock($view, 'form', $vars);
    }

    protected function renderLabel(FormView $view, $label = null, array $vars = array())
    {
        if ($label !== null) {
            $vars += array('label' => $label);
        }

        return (string) $this->environment->getRuntime('form')->searchAndRenderBlock($view, 'label', $vars);
    }

    protected function renderErrors(FormView $view)
    {
        return (string) $this->environment->getRuntime('form')->searchAndRenderBlock($view, 'errors');
    }

    protected function renderWidget(FormView $view, array $vars = array())
    {
        return (string) $this->environment->getRuntime('form')->searchAndRenderBlock($view, 'widget', $vars);
    }

    protected function renderRow(FormView $view, array $vars = array())
    {
        return (string) $this->environment->getRuntime('form')->searchAndRenderBlock($view, 'row', $vars);
    }

    protected function renderRest(FormView $view, array $vars = array())
    {
        return (string) $this->environment->getRuntime('form')->searchAndRenderBlock($view, 'rest', $vars);
    }

    protected function renderStart(FormView $view, array $vars = array())
    {
        return (string) $this->environment->getRuntime('form')->renderBlock($view, 'form_start', $vars);
    }

    protected function renderEnd(FormView $view, array $vars = array())
    {
        return (string) $this->environment->getRuntime('form')->renderBlock($view, 'form_end', $vars);
    }

    protected function setTheme(FormView $view, array $themes)
    {
        $this->environment->getRuntime('form')->setTheme($view, $themes);
    }
}
