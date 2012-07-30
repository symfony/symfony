<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Templating;

use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Extension\Templating\FormHelper;
use Symfony\Component\Form\Extension\Templating\TemplatingRendererEngine;
use Symfony\Component\Form\Tests\AbstractTableLayoutTest;
use Symfony\Component\Form\Tests\Extension\Templating\Fixtures\StubTemplateNameParser;
use Symfony\Component\Form\Tests\Extension\Templating\Fixtures\StubTranslator;
use Symfony\Component\Templating\PhpEngine;
use Symfony\Component\Templating\Loader\FilesystemLoader;

// should probably be moved to the Translation component
use Symfony\Bundle\FrameworkBundle\Templating\Helper\TranslatorHelper;

class FormHelperTableLayoutTest extends AbstractTableLayoutTest
{
    protected $helper;

    protected function setUp()
    {
        if (!class_exists('Symfony\Bundle\FrameworkBundle\Templating\Helper\TranslatorHelper')) {
            $this->markTestSkipped('The "FrameworkBundle" is not available');
        }

        if (!class_exists('Symfony\Component\Templating\PhpEngine')) {
            $this->markTestSkipped('The "Templating" component is not available');
        }

        parent::setUp();

        // should be moved to the Form component once absolute file paths are supported
        // by the default name parser in the Templating component
        $reflClass = new \ReflectionClass('Symfony\Bundle\FrameworkBundle\FrameworkBundle');
        $root = realpath(dirname($reflClass->getFileName()) . '/Resources/views');
        $rootTheme = realpath(__DIR__.'/Resources');
        $templateNameParser = new StubTemplateNameParser($root, $rootTheme);
        $loader = new FilesystemLoader(array());
        $engine = new PhpEngine($templateNameParser, $loader);
        $engine->addGlobal('global', '');
        $rendererEngine = new TemplatingRendererEngine($engine, array(
            'FrameworkBundle:Form',
            'FrameworkBundle:FormTable'
        ));
        $renderer = new FormRenderer($rendererEngine, $this->getMock('Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface'));

        $this->helper = new FormHelper($renderer);

        $engine->setHelpers(array(
            $this->helper,
            new TranslatorHelper(new StubTranslator()),
        ));
    }

    protected function tearDown()
    {
        $this->helper = null;
    }

    protected function renderEnctype(FormView $view)
    {
        return (string) $this->helper->enctype($view);
    }

    protected function renderLabel(FormView $view, $label = null, array $vars = array())
    {
        return (string) $this->helper->label($view, $label, $vars);
    }

    protected function renderErrors(FormView $view)
    {
        return (string) $this->helper->errors($view);
    }

    protected function renderWidget(FormView $view, array $vars = array())
    {
        return (string) $this->helper->widget($view, $vars);
    }

    protected function renderRow(FormView $view, array $vars = array())
    {
        return (string) $this->helper->row($view, $vars);
    }

    protected function renderRest(FormView $view, array $vars = array())
    {
        return (string) $this->helper->rest($view, $vars);
    }

    protected function setTheme(FormView $view, array $themes)
    {
        $this->helper->setTheme($view, $themes);
    }
}
