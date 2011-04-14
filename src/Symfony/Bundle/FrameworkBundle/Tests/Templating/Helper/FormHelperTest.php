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
use Symfony\Component\Form\TemplateContext;
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

    protected function renderEnctype(TemplateContext $context)
    {
        return (string)$this->helper->enctype($context);
    }

    protected function renderLabel(TemplateContext $context, $label = null)
    {
        return (string)$this->helper->label($context, $label);
    }

    protected function renderErrors(TemplateContext $context)
    {
        return (string)$this->helper->errors($context);
    }

    protected function renderWidget(TemplateContext $context, array $vars = array())
    {
        return (string)$this->helper->widget($context, $vars);
    }

    protected function renderRow(TemplateContext $context, array $vars = array())
    {
        return (string)$this->helper->row($context, $vars);
    }

    protected function renderRest(TemplateContext $context, array $vars = array())
    {
        return (string)$this->helper->rest($context, $vars);
    }

}