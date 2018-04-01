<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symphony\Bridge\Twig\Extension\TranslationExtension;
use Symphony\Component\Translation\Translator;
use Symphony\Component\Translation\Loader\ArrayLoader;
use Twig\Environment;
use Twig\Loader\ArrayLoader as TwigArrayLoader;

class TranslationExtensionTest extends TestCase
{
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
            echo $template."\n";
            $loader = new TwigArrayLoader(array('index' => $template));
            $twig = new Environment($loader, array('debug' => true, 'cache' => false));
            $twig->addExtension(new TranslationExtension(new Translator('en')));

            echo $twig->compile($twig->parse($twig->tokenize($twig->getLoader()->getSourceContext('index'))))."\n\n";
            $this->assertEquals($expected, $this->getTemplate($template)->render($variables));
        }

        $this->assertEquals($expected, $this->getTemplate($template)->render($variables));
    }

    /**
     * @expectedException        \Twig\Error\SyntaxError
     * @expectedExceptionMessage Unexpected token. Twig was looking for the "with", "from", or "into" keyword in "index" at line 3.
     */
    public function testTransUnknownKeyword()
    {
        $output = $this->getTemplate("{% trans \n\nfoo %}{% endtrans %}")->render();
    }

    /**
     * @expectedException        \Twig\Error\SyntaxError
     * @expectedExceptionMessage A message inside a trans tag must be a simple text in "index" at line 2.
     */
    public function testTransComplexBody()
    {
        $output = $this->getTemplate("{% trans %}\n{{ 1 + 2 }}{% endtrans %}")->render();
    }

    /**
     * @expectedException        \Twig\Error\SyntaxError
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
            array('{% trans %}%name%{% endtrans %}', 'Symphony', array('name' => 'Symphony')),

            array('{% trans from elsewhere %}Hello{% endtrans %}', 'Hello'),

            array('{% trans %}Hello %name%{% endtrans %}', 'Hello Symphony', array('name' => 'Symphony')),
            array('{% trans with { \'%name%\': \'Symphony\' } %}Hello %name%{% endtrans %}', 'Hello Symphony'),
            array('{% set vars = { \'%name%\': \'Symphony\' } %}{% trans with vars %}Hello %name%{% endtrans %}', 'Hello Symphony'),

            array('{% trans into "fr"%}Hello{% endtrans %}', 'Hello'),

            // transchoice
            array(
                '{% transchoice count from "messages" %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is no apples',
                array('count' => 0),
            ),
            array(
                '{% transchoice count %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is 5 apples',
                array('count' => 5),
            ),
            array(
                '{% transchoice count %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtranschoice %}',
                'There is 5 apples (Symphony)',
                array('count' => 5, 'name' => 'Symphony'),
            ),
            array(
                '{% transchoice count with { \'%name%\': \'Symphony\' } %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtranschoice %}',
                'There is 5 apples (Symphony)',
                array('count' => 5),
            ),
            array(
                '{% transchoice count into "fr"%}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is no apples',
                array('count' => 0),
            ),
            array(
                '{% transchoice 5 into "fr"%}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is 5 apples',
            ),

            // trans filter
            array('{{ "Hello"|trans }}', 'Hello'),
            array('{{ name|trans }}', 'Symphony', array('name' => 'Symphony')),
            array('{{ hello|trans({ \'%name%\': \'Symphony\' }) }}', 'Hello Symphony', array('hello' => 'Hello %name%')),
            array('{% set vars = { \'%name%\': \'Symphony\' } %}{{ hello|trans(vars) }}', 'Hello Symphony', array('hello' => 'Hello %name%')),
            array('{{ "Hello"|trans({}, "messages", "fr") }}', 'Hello'),

            // transchoice filter
            array('{{ "{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples"|transchoice(count) }}', 'There is 5 apples', array('count' => 5)),
            array('{{ text|transchoice(5, {\'%name%\': \'Symphony\'}) }}', 'There is 5 apples (Symphony)', array('text' => '{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%)')),
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

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foo (messages)'), 'en');
        $translator->addResource('array', array('foo' => 'foo (custom)'), 'en', 'custom');
        $translator->addResource('array', array('foo' => 'foo (foo)'), 'en', 'foo');

        $template = $this->getTemplate($templates, $translator);

        $this->assertEquals('foo (foo)foo (custom)foo (foo)foo (custom)foo (foo)foo (custom)', trim($template->render(array())));
    }

    public function testDefaultTranslationDomainWithNamedArguments()
    {
        $templates = array(
            'index' => '
                {%- trans_default_domain "foo" %}

                {%- block content %}
                    {{- "foo"|trans(arguments = {}, domain = "custom") }}
                    {{- "foo"|transchoice(count = 1) }}
                    {{- "foo"|transchoice(count = 1, arguments = {}, domain = "custom") }}
                    {{- "foo"|trans({}, domain = "custom") }}
                    {{- "foo"|trans({}, "custom", locale = "fr") }}
                    {{- "foo"|transchoice(1, arguments = {}, domain = "custom") }}
                    {{- "foo"|transchoice(1, {}, "custom", locale = "fr") }}
                {% endblock %}
            ',

            'base' => '
                {%- block content "" %}
            ',
        );

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foo (messages)'), 'en');
        $translator->addResource('array', array('foo' => 'foo (custom)'), 'en', 'custom');
        $translator->addResource('array', array('foo' => 'foo (foo)'), 'en', 'foo');
        $translator->addResource('array', array('foo' => 'foo (fr)'), 'fr', 'custom');

        $template = $this->getTemplate($templates, $translator);

        $this->assertEquals('foo (custom)foo (foo)foo (custom)foo (custom)foo (fr)foo (custom)foo (fr)', trim($template->render(array())));
    }

    protected function getTemplate($template, $translator = null)
    {
        if (null === $translator) {
            $translator = new Translator('en');
        }

        if (is_array($template)) {
            $loader = new TwigArrayLoader($template);
        } else {
            $loader = new TwigArrayLoader(array('index' => $template));
        }
        $twig = new Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new TranslationExtension($translator));

        return $twig->loadTemplate('index');
    }
}
