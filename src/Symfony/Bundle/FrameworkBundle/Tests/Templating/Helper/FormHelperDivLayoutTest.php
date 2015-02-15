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

use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Templating\TemplatingExtension;
use Symfony\Component\Form\Tests\AbstractDivLayoutTest;
use Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper\Fixtures\StubTemplateNameParser;
use Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper\Fixtures\StubTranslator;
use Symfony\Component\Templating\PhpEngine;
use Symfony\Component\Templating\Loader\FilesystemLoader;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\TranslatorHelper;

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
        $root = realpath(dirname($reflClass->getFileName()).'/Resources/views');
        $rootTheme = realpath(__DIR__.'/Resources');
        $templateNameParser = new StubTemplateNameParser($root, $rootTheme);
        $loader = new FilesystemLoader(array());

        $this->engine = new PhpEngine($templateNameParser, $loader);
        $this->engine->addGlobal('global', '');
        $this->engine->setHelpers(array(
            new TranslatorHelper(new StubTranslator()),
        ));

        return array_merge(parent::getExtensions(), array(
            new TemplatingExtension($this->engine, $this->csrfTokenManager, array(
                'FrameworkBundle:Form',
            )),
        ));
    }

    protected function tearDown()
    {
        $this->engine = null;

        parent::tearDown();
    }

    protected function renderForm(FormView $view, array $vars = array())
    {
        return (string) $this->engine->get('form')->form($view, $vars);
    }

<<<<<<< HEAD
    protected function renderEnctype(FormView $view)
    {
        if (!method_exists($form = $this->engine->get('form'), 'enctype')) {
            $this->markTestSkipped(sprintf("Deprecated method %s->enctype() is not implemented.", get_class($form)));
        }

        return (string) $form->enctype($view);
    }

=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
    protected function renderLabel(FormView $view, $label = null, array $vars = array())
    {
        return (string) $this->engine->get('form')->label($view, $label, $vars);
    }

    protected function renderErrors(FormView $view)
    {
        return (string) $this->engine->get('form')->errors($view);
    }

    protected function renderWidget(FormView $view, array $vars = array())
    {
        return (string) $this->engine->get('form')->widget($view, $vars);
    }

    protected function renderRow(FormView $view, array $vars = array())
    {
        return (string) $this->engine->get('form')->row($view, $vars);
    }

    protected function renderRest(FormView $view, array $vars = array())
    {
        return (string) $this->engine->get('form')->rest($view, $vars);
    }

    protected function renderStart(FormView $view, array $vars = array())
    {
        return (string) $this->engine->get('form')->start($view, $vars);
    }

    protected function renderEnd(FormView $view, array $vars = array())
    {
        return (string) $this->engine->get('form')->end($view, $vars);
    }

    protected function setTheme(FormView $view, array $themes)
    {
        $this->engine->get('form')->setTheme($view, $themes);
    }

    public static function themeBlockInheritanceProvider()
    {
        return array(
            array(array('TestBundle:Parent')),
        );
    }

    public static function themeInheritanceProvider()
    {
        return array(
            array(array('TestBundle:Parent'), array('TestBundle:Child')),
        );
    }
}
