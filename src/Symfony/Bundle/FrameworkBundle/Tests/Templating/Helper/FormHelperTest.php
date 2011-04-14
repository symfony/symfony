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

require_once __DIR__.'/Fixtures/StubTemplateNameParser.php';
require_once __DIR__.'/Fixtures/StubTranslator.php';

use Symfony\Bundle\FrameworkBundle\Templating\Helper\FormHelper;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\TranslatorHelper;
use Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper\Fixtures\StubTemplateNameParser;
use Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper\Fixtures\StubTranslator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Templating\PhpEngine;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\Loader\FilesystemLoader;
use Symfony\Tests\Component\Form\AbstractDivLayoutTest;

class FormHelperTest extends AbstractDivLayoutTest
{
    protected $helper;

    protected function setUp()
    {
        parent::setUp();

        $root = realpath(__DIR__.'/../../../Resources/views/Form');
        $templateNameParser = new StubTemplateNameParser($root);
        $loader = new FilesystemLoader(array());
        $engine = new PhpEngine($templateNameParser, $loader);

        $this->helper = new FormHelper($engine);

        $engine->setHelpers(array(
            $this->helper,
            new TranslatorHelper(new StubTranslator()),
        ));
    }

    protected function renderEnctype(FormInterface $form)
    {
        return (string)$this->helper->enctype($form->getContext());
    }

    protected function renderLabel(FormInterface $form, $label = null)
    {
        return (string)$this->helper->label($form->getContext(), $label);
    }

    protected function renderErrors(FormInterface $form)
    {
        return (string)$this->helper->errors($form->getContext());
    }

    protected function renderWidget(FormInterface $form, array $vars = array())
    {
        return (string)$this->helper->widget($form->getContext(), $vars);
    }

    protected function renderRow(FormInterface $form, array $vars = array())
    {
        return (string)$this->helper->row($form->getContext(), $vars);
    }

    protected function renderRest(FormInterface $form, array $vars = array())
    {
        return (string)$this->helper->rest($form->getContext(), $vars);
    }

}