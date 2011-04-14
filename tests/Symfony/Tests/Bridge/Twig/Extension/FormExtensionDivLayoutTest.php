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

use Symfony\Component\Form\TemplateContext;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Tests\Component\Form\AbstractDivLayoutTest;
use Symfony\Tests\Bridge\Twig\Extension\Fixtures\StubTranslator;
use Symfony\Tests\Bridge\Twig\Extension\Fixtures\StubFilesystemLoader;

class FormExtensionDivLayoutTest extends AbstractDivLayoutTest
{
    protected function setUp()
    {
        parent::setUp();

        $loader = new StubFilesystemLoader(array(
            __DIR__.'/../../../../../../src/Symfony/Bundle/TwigBundle/Resources/views',
        ));

        $this->extension = new FormExtension(array('div_layout.html.twig'));

        $environment = new \Twig_Environment($loader);
        $environment->addExtension($this->extension);
        $environment->addExtension(new TranslationExtension(new StubTranslator()));

        $this->extension->initRuntime($environment);
    }

    protected function renderEnctype(TemplateContext $context)
    {
        return (string)$this->extension->renderEnctype($context);
    }

    protected function renderLabel(TemplateContext $context, $label = null)
    {
        return (string)$this->extension->renderLabel($context, $label);
    }

    protected function renderErrors(TemplateContext $context)
    {
        return (string)$this->extension->renderErrors($context);
    }

    protected function renderWidget(TemplateContext $context, array $vars = array())
    {
        return (string)$this->extension->renderWidget($context, $vars);
    }

    protected function renderRow(TemplateContext $context, array $vars = array())
    {
        return (string)$this->extension->renderRow($context, $vars);
    }

    protected function renderRest(TemplateContext $context, array $vars = array())
    {
        return (string)$this->extension->renderRest($context, $vars);
    }
}
