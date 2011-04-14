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

use Symfony\Component\Form\FormInterface;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Tests\Component\Form\AbstractTableLayoutTest;
use Symfony\Tests\Bridge\Twig\Extension\Fixtures\StubTranslator;
use Symfony\Tests\Bridge\Twig\Extension\Fixtures\StubFilesystemLoader;

class FormExtensionTableLayoutTest extends AbstractTableLayoutTest
{
    protected function setUp()
    {
        parent::setUp();

        $loader = new StubFilesystemLoader(array(
            __DIR__.'/../../../../../../src/Symfony/Bundle/TwigBundle/Resources/views',
        ));

        $this->extension = new FormExtension(array('table_layout.html.twig'));

        $environment = new \Twig_Environment($loader);
        $environment->addExtension($this->extension);
        $environment->addExtension(new TranslationExtension(new StubTranslator()));

        $this->extension->initRuntime($environment);
    }

    protected function renderEnctype(FormInterface $form)
    {
        return (string)$this->extension->renderEnctype($form->getContext());
    }

    protected function renderLabel(FormInterface $form, $label = null)
    {
        return (string)$this->extension->renderLabel($form->getContext(), $label);
    }

    protected function renderErrors(FormInterface $form)
    {
        return (string)$this->extension->renderErrors($form->getContext());
    }

    protected function renderWidget(FormInterface $form, array $vars = array())
    {
        return (string)$this->extension->renderWidget($form->getContext(), $vars);
    }

    protected function renderRow(FormInterface $form, array $vars = array())
    {
        return (string)$this->extension->renderRow($form->getContext(), $vars);
    }

    protected function renderRest(FormInterface $form, array $vars = array())
    {
        return (string)$this->extension->renderRest($form->getContext(), $vars);
    }
}
