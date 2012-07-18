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
use Symfony\Component\Form\Tests\AbstractDivLayoutTest;

class FormExtensionDivLayoutTest extends AbstractDivLayoutTest
{
    /**
     * @var FormExtension
     */
    protected $extension;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Locale\Locale')) {
            $this->markTestSkipped('The "Locale" component is not available');
        }

        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        if (!class_exists('Symfony\Component\Form\Form')) {
            $this->markTestSkipped('The "Form" component is not available');
        }

        if (!class_exists('Twig_Environment')) {
            $this->markTestSkipped('Twig is not available.');
        }

        parent::setUp();

        $rendererEngine = new TwigRendererEngine(array(
            'form_div_layout.html.twig',
            'custom_widgets.html.twig',
        ));
        $renderer = new TwigRenderer($rendererEngine, $this->getMock('Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface'));

        $this->extension = new FormExtension($renderer);

        $loader = new StubFilesystemLoader(array(
            __DIR__.'/../../Resources/views/Form',
            __DIR__,
        ));

        $environment = new \Twig_Environment($loader, array('strict_variables' => true));
        $environment->addExtension(new TranslationExtension(new StubTranslator()));
        $environment->addGlobal('global', '');
        $environment->addExtension($this->extension);

        $this->extension->initRuntime($environment);
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->extension = null;
    }

    public function testThemeBlockInheritanceUsingUse()
    {
        $view = $this->factory
            ->createNamed('name', 'email')
            ->createView()
        ;

        $this->setTheme($view, array('theme_use.html.twig'));

        $this->assertMatchesXpath(
            $this->renderWidget($view),
            '/input[@type="email"][@rel="theme"]'
        );
    }

    public function testThemeBlockInheritanceUsingExtend()
    {
        $view = $this->factory
            ->createNamed('name', 'email')
            ->createView()
        ;

        $this->setTheme($view, array('theme_extends.html.twig'));

        $this->assertMatchesXpath(
            $this->renderWidget($view),
            '/input[@type="email"][@rel="theme"]'
        );
    }

    protected function renderEnctype(FormView $view)
    {
        return (string) $this->extension->renderer->renderEnctype($view);
    }

    protected function renderLabel(FormView $view, $label = null, array $vars = array())
    {
        return (string) $this->extension->renderer->renderLabel($view, $label, $vars);
    }

    protected function renderErrors(FormView $view)
    {
        return (string) $this->extension->renderer->renderErrors($view);
    }

    protected function renderWidget(FormView $view, array $vars = array())
    {
        return (string) $this->extension->renderer->renderWidget($view, $vars);
    }

    protected function renderRow(FormView $view, array $vars = array())
    {
        return (string) $this->extension->renderer->renderRow($view, $vars);
    }

    protected function renderRest(FormView $view, array $vars = array())
    {
        return (string) $this->extension->renderer->renderRest($view, $vars);
    }

    protected function setTheme(FormView $view, array $themes)
    {
        $this->extension->renderer->setTheme($view, $themes);
    }

    public static function themeBlockInheritanceProvider()
    {
        return array(
            array(array('theme.html.twig'))
        );
    }

    public static function themeInheritanceProvider()
    {
        return array(
            array(array('parent_label.html.twig'), array('child_label.html.twig'))
        );
    }
}
