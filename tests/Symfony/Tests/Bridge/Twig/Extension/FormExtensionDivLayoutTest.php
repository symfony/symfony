<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Twig\Extension;

require_once __DIR__.'/Fixtures/StubTranslator.php';
require_once __DIR__.'/Fixtures/StubFilesystemLoader.php';

use Symfony\Component\Form\FormView;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Tests\Component\Form\AbstractDivLayoutTest;
use Symfony\Tests\Bridge\Twig\Extension\Fixtures\StubTranslator;
use Symfony\Tests\Bridge\Twig\Extension\Fixtures\StubFilesystemLoader;

class FormExtensionDivLayoutTest extends AbstractDivLayoutTest
{
    protected function setUp()
    {
        if (!class_exists('Twig_Environment')) {
            $this->markTestSkipped('Twig is not available.');
        }

        parent::setUp();

        $loader = new StubFilesystemLoader(array(
            __DIR__.'/../../../../../../src/Symfony/Bridge/Twig/Resources/views/Form',
            __DIR__,
        ));

        $this->extension = new FormExtension(array(
            'form_div_layout.html.twig',
            'custom_widgets.html.twig',
        ));

        $environment = new \Twig_Environment($loader);
        $environment->addExtension($this->extension);
        $environment->addExtension(new TranslationExtension(new StubTranslator()));

        $this->extension->initRuntime($environment);
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->extension = null;
    }

    public function testThemeBlockInheritance()
    {
        $view = $this->factory
            ->createNamed('email', 'name')
            ->createView()
        ;

        $this->extension->setTheme($view, array('theme.html.twig'));

        $this->assertMatchesXpath(
            $this->renderWidget($view),
            '/input[@type="email"][@rel="theme"]'
        );
    }

    public function testThemeBlockInheritanceUsingUse()
    {
        $view = $this->factory
            ->createNamed('email', 'name')
            ->createView()
        ;

        $this->extension->setTheme($view, array('theme_use.html.twig'));

        $this->assertMatchesXpath(
            $this->renderWidget($view),
            '/input[@type="email"][@rel="theme"]'
        );
    }

    public function testThemeBlockInheritanceUsingExtend()
    {
        $view = $this->factory
            ->createNamed('email', 'name')
            ->createView()
        ;

        $this->extension->setTheme($view, array('theme_extends.html.twig'));

        $this->assertMatchesXpath(
            $this->renderWidget($view),
            '/input[@type="email"][@rel="theme"]'
        );
    }

    public function testThemeInheritance()
    {
        $child = $this->factory->createNamedBuilder('form', 'child')
            ->add('field', 'text')
            ->getForm();

        $view = $this->factory->createNamedBuilder('form', 'parent')
            ->add('field', 'text')
            ->getForm()
            ->add($child)
            ->createView()
        ;

        $this->extension->setTheme($view, array('parent_label.html.twig'));
        $this->extension->setTheme($view['child'], array('child_label.html.twig'));

        $this->assertWidgetMatchesXpath($view, array(),
'/div
    [
        ./input[@type="hidden"]
        /following-sibling::div
            [
                ./label[.="parent"]
                /following-sibling::input[@type="text"]
            ]
        /following-sibling::div
            [
                ./label
                /following-sibling::div
                    [
                        ./div
                            [
                                ./label[.="child"]
                                /following-sibling::input[@type="text"]
                            ]
                    ]
            ]
    ]
'
        );
    }

    protected function renderEnctype(FormView $view)
    {
        return (string)$this->extension->renderEnctype($view);
    }

    protected function renderLabel(FormView $view, $label = null, array $vars = array())
    {
        return (string)$this->extension->renderLabel($view, $label, $vars);
    }

    protected function renderErrors(FormView $view)
    {
        return (string)$this->extension->renderErrors($view);
    }

    protected function renderWidget(FormView $view, array $vars = array())
    {
        return (string)$this->extension->renderWidget($view, $vars);
    }

    protected function renderRow(FormView $view, array $vars = array())
    {
        return (string)$this->extension->renderRow($view, $vars);
    }

    protected function renderRest(FormView $view, array $vars = array())
    {
        return (string)$this->extension->renderRest($view, $vars);
    }
}
