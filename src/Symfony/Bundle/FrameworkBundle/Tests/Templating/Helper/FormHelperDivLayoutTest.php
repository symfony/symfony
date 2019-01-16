<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper;

use Symfony\Bundle\FrameworkBundle\Templating\Helper\TranslatorHelper;
use Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper\Fixtures\StubTemplateNameParser;
use Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper\Fixtures\StubTranslator;
use Symfony\Component\Form\Extension\Templating\TemplatingExtension;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Tests\AbstractDivLayoutTest;
use Symfony\Component\Templating\Loader\FilesystemLoader;
use Symfony\Component\Templating\PhpEngine;

class FormHelperDivLayoutTest extends AbstractDivLayoutTest
{
    /**
     * @var PhpEngine
     */
    protected $engine;

    protected function getExtensions()
    {
        // should be moved to the Form component once absolute file paths are supported
        // by the default name parser in the Templating component
        $reflClass = new \ReflectionClass('Symfony\Bundle\FrameworkBundle\FrameworkBundle');
        $root = realpath(\dirname($reflClass->getFileName()).'/Resources/views');
        $rootTheme = realpath(__DIR__.'/Resources');
        $templateNameParser = new StubTemplateNameParser($root, $rootTheme);
        $loader = new FilesystemLoader([]);

        $this->engine = new PhpEngine($templateNameParser, $loader);
        $this->engine->addGlobal('global', '');
        $this->engine->setHelpers([
            new TranslatorHelper(new StubTranslator()),
        ]);

        return array_merge(parent::getExtensions(), [
            new TemplatingExtension($this->engine, $this->csrfTokenManager, [
                'FrameworkBundle:Form',
            ]),
        ]);
    }

    protected function tearDown()
    {
        $this->engine = null;

        parent::tearDown();
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

    public function testMoneyWidgetInIso()
    {
        $this->engine->setCharset('ISO-8859-1');

        $view = $this->factory
            ->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\MoneyType')
            ->createView()
        ;

        $this->assertSame('&euro; <input type="text" id="name" name="name" required="required" />', $this->renderWidget($view));
    }

    protected function renderForm(FormView $view, array $vars = [])
    {
        return (string) $this->engine->get('form')->form($view, $vars);
    }

    protected function renderLabel(FormView $view, $label = null, array $vars = [])
    {
        return (string) $this->engine->get('form')->label($view, $label, $vars);
    }

    protected function renderErrors(FormView $view)
    {
        return (string) $this->engine->get('form')->errors($view);
    }

    protected function renderWidget(FormView $view, array $vars = [])
    {
        return (string) $this->engine->get('form')->widget($view, $vars);
    }

    protected function renderRow(FormView $view, array $vars = [])
    {
        return (string) $this->engine->get('form')->row($view, $vars);
    }

    protected function renderRest(FormView $view, array $vars = [])
    {
        return (string) $this->engine->get('form')->rest($view, $vars);
    }

    protected function renderStart(FormView $view, array $vars = [])
    {
        return (string) $this->engine->get('form')->start($view, $vars);
    }

    protected function renderEnd(FormView $view, array $vars = [])
    {
        return (string) $this->engine->get('form')->end($view, $vars);
    }

    protected function setTheme(FormView $view, array $themes, $useDefaultThemes = true)
    {
        $this->engine->get('form')->setTheme($view, $themes, $useDefaultThemes);
    }

    public static function themeBlockInheritanceProvider()
    {
        return [
            [['TestBundle:Parent']],
        ];
    }

    public static function themeInheritanceProvider()
    {
        return [
            [['TestBundle:Parent'], ['TestBundle:Child']],
        ];
    }
}
