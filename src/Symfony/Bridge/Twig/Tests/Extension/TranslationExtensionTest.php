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

use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Bridge\Twig\Tests\TestCase;

class TranslationExtensionTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Symfony\Component\Translation\Translator')) {
            $this->markTestSkipped('The "Translation" component is not available');
        }

        if (!class_exists('Twig_Environment')) {
            $this->markTestSkipped('Twig is not available.');
        }
    }

    public function testEscaping()
    {
        $output = $this->getTemplate('{% trans %}Percent: %value%%% (%msg%){% endtrans %}')->render(array('value' => 12, 'msg' => 'approx.'));

        $this->assertEquals('Percent: 12% (approx.)', $output);
    }

    /**
     * @dataProvider getTransTests
     */
    public function testTrans($template, $expected, array $variables = array())
    {
        if ($expected != $this->getTemplate($template)->render($variables)) {
            print $template."\n";
            $loader = new \Twig_Loader_Array(array('index' => $template));
            $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
            $twig->addExtension(new TranslationExtension(new Translator('en', new MessageSelector())));

            echo $twig->compile($twig->parse($twig->tokenize($twig->getLoader()->getSource('index'), 'index')))."\n\n";
            $this->assertEquals($expected, $this->getTemplate($template)->render($variables));
        }

        $this->assertEquals($expected, $this->getTemplate($template)->render($variables));
    }

    /**
     * @expectedException        \Twig_Error_Syntax
     * @expectedExceptionMessage Unexpected token. Twig was looking for the "with", "from", or "into" keyword in "index" at line 3.
     */
    public function testTransUnknownKeyword()
    {
        $output = $this->getTemplate("{% trans \n\nfoo %}{% endtrans %}")->render();
    }

    /**
     * @expectedException        \Twig_Error_Syntax
     * @expectedExceptionMessage A message inside a trans tag must be a simple text in "index" at line 2.
     */
    public function testTransComplexBody()
    {
        $output = $this->getTemplate("{% trans %}\n{{ 1 + 2 }}{% endtrans %}")->render();
    }

    /**
     * @expectedException        \Twig_Error_Syntax
     * @expectedExceptionMessage A message inside a transchoice tag must be a simple text in "index" at line 2.
     */
    public function testTransChoiceComplexBody()
    {
        $output = $this->getTemplate("{% transchoice count %}\n{{ 1 + 2 }}{% endtranschoice %}")->render();
    }

    public function getTransTests()
    {
        return array(
            // trans tag
            array('{% trans %}Hello{% endtrans %}', 'Hello'),
            array('{% trans %}%name%{% endtrans %}', 'Symfony', array('name' => 'Symfony')),

            array('{% trans from elsewhere %}Hello{% endtrans %}', 'Hello'),

            array('{% trans %}Hello %name%{% endtrans %}', 'Hello Symfony', array('name' => 'Symfony')),
            array('{% trans with { \'%name%\': \'Symfony\' } %}Hello %name%{% endtrans %}', 'Hello Symfony'),
            array('{% set vars = { \'%name%\': \'Symfony\' } %}{% trans with vars %}Hello %name%{% endtrans %}', 'Hello Symfony'),

            array('{% trans into "fr"%}Hello{% endtrans %}', 'Hello'),

            // transchoice
            array('{% transchoice count from "messages" %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is no apples', array('count' => 0),),
            array('{% transchoice count %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is 5 apples', array('count' => 5),),
            array('{% transchoice count %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtranschoice %}',
                'There is 5 apples (Symfony)', array('count' => 5, 'name' => 'Symfony'),),
            array('{% transchoice count with { \'%name%\': \'Symfony\' } %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtranschoice %}',
                'There is 5 apples (Symfony)', array('count' => 5),),
            array('{% transchoice count into "fr"%}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is no apples', array('count' => 0),),
            array('{% transchoice 5 into "fr"%}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is 5 apples',),

            // trans filter
            array('{{ "Hello"|trans }}', 'Hello'),
            array('{{ name|trans }}', 'Symfony', array('name' => 'Symfony')),
            array('{{ hello|trans({ \'%name%\': \'Symfony\' }) }}', 'Hello Symfony', array('hello' => 'Hello %name%')),
            array('{% set vars = { \'%name%\': \'Symfony\' } %}{{ hello|trans(vars) }}', 'Hello Symfony', array('hello' => 'Hello %name%')),
            array('{{ "Hello"|trans({}, "messages", "fr") }}', 'Hello'),

            // transchoice filter
            array('{{ "{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples"|transchoice(count) }}', 'There is 5 apples', array('count' => 5)),
            array('{{ text|transchoice(5, {\'%name%\': \'Symfony\'}) }}', 'There is 5 apples (Symfony)', array('text' => '{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%)')),
            array('{{ "{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples"|transchoice(count, {}, "messages", "fr") }}', 'There is 5 apples', array('count' => 5)),
        );
    }

    public function testDefaultTranslationDomain()
    {
        $templates = array(
            'index' => '
                {%- extends "base" %}

                {%- trans_default_domain "foo" %}

                {%- block content %}
                    {%- trans %}foo{% endtrans %}
                    {%- trans from "custom" %}foo{% endtrans %}
                    {{- "foo"|trans }}
                    {{- "foo"|trans({}, "custom") }}
                    {{- "foo"|transchoice(1) }}
                    {{- "foo"|transchoice(1, {}, "custom") }}
                {% endblock %}
            ',

            'base' => '
                {%- block content "" %}
            ',
        );

        $translator = new Translator('en', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foo (messages)'), 'en');
        $translator->addResource('array', array('foo' => 'foo (custom)'), 'en', 'custom');
        $translator->addResource('array', array('foo' => 'foo (foo)'), 'en', 'foo');

        $template = $this->getTemplate($templates, $translator);

        $this->assertEquals('foo (foo)foo (custom)foo (foo)foo (custom)foo (foo)foo (custom)', trim($template->render(array())));
    }

    protected function getTemplate($template, $translator = null)
    {
        if (null === $translator) {
            $translator = new Translator('en', new MessageSelector());
        }

        if (is_array($template)) {
            $loader = new \Twig_Loader_Array($template);
        } else {
            $loader = new \Twig_Loader_Array(array('index' => $template));
        }
        $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new TranslationExtension($translator));

        return $twig->loadTemplate('index');
    }
}
