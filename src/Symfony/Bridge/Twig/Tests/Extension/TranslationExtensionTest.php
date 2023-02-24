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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader as TwigArrayLoader;
use Twig\TemplateWrapper;

class TranslationExtensionTest extends TestCase
{
    public function testEscaping()
    {
        $output = $this->getTemplate('{% trans %}Percent: %value%%% (%msg%){% endtrans %}')->render(['value' => 12, 'msg' => 'approx.']);

        $this->assertEquals('Percent: 12% (approx.)', $output);
    }

    /**
     * @dataProvider getTransTests
     */
    public function testTrans($template, $expected, array $variables = [])
    {
        if ($expected != $this->getTemplate($template)->render($variables)) {
            echo $template."\n";
            $loader = new TwigArrayLoader(['index' => $template]);
            $twig = new Environment($loader, ['debug' => true, 'cache' => false]);
            $twig->addExtension(new TranslationExtension(new Translator('en')));

            echo $twig->compile($twig->parse($twig->tokenize($twig->getLoader()->getSourceContext('index'))))."\n\n";
            $this->assertEquals($expected, $this->getTemplate($template)->render($variables));
        }

        $this->assertEquals($expected, $this->getTemplate($template)->render($variables));
    }

    public function testTransUnknownKeyword()
    {
        $this->expectException(\Twig\Error\SyntaxError::class);
        $this->expectExceptionMessage('Unexpected token. Twig was looking for the "with", "from", or "into" keyword in "index" at line 3.');
        $this->getTemplate("{% trans \n\nfoo %}{% endtrans %}")->render();
    }

    public function testTransComplexBody()
    {
        $this->expectException(\Twig\Error\SyntaxError::class);
        $this->expectExceptionMessage('A message inside a trans tag must be a simple text in "index" at line 2.');
        $this->getTemplate("{% trans %}\n{{ 1 + 2 }}{% endtrans %}")->render();
    }

    public static function getTransTests()
    {
        return [
            // trans tag
            ['{% trans %}Hello{% endtrans %}', 'Hello'],
            ['{% trans %}%name%{% endtrans %}', 'Symfony', ['name' => 'Symfony']],

            ['{% trans from elsewhere %}Hello{% endtrans %}', 'Hello'],

            ['{% trans %}Hello %name%{% endtrans %}', 'Hello Symfony', ['name' => 'Symfony']],
            ['{% trans with { \'%name%\': \'Symfony\' } %}Hello %name%{% endtrans %}', 'Hello Symfony'],
            ['{% set vars = { \'%name%\': \'Symfony\' } %}{% trans with vars %}Hello %name%{% endtrans %}', 'Hello Symfony'],

            ['{% trans into "fr"%}Hello{% endtrans %}', 'Hello'],

            // trans with count
            [
                '{% trans from "messages" %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtrans %}',
                'There is no apples',
                ['count' => 0],
            ],
            [
                '{% trans %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtrans %}',
                'There is 5 apples',
                ['count' => 5],
            ],
            [
                '{% trans %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtrans %}',
                'There is 5 apples (Symfony)',
                ['count' => 5, 'name' => 'Symfony'],
            ],
            [
                '{% trans with { \'%name%\': \'Symfony\' } %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtrans %}',
                'There is 5 apples (Symfony)',
                ['count' => 5],
            ],
            [
                '{% trans into "fr"%}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtrans %}',
                'There is no apples',
                ['count' => 0],
            ],
            [
                '{% trans count 5 into "fr"%}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtrans %}',
                'There is 5 apples',
            ],

            // trans filter
            ['{{ "Hello"|trans }}', 'Hello'],
            ['{{ name|trans }}', 'Symfony', ['name' => 'Symfony']],
            ['{{ hello|trans({ \'%name%\': \'Symfony\' }) }}', 'Hello Symfony', ['hello' => 'Hello %name%']],
            ['{% set vars = { \'%name%\': \'Symfony\' } %}{{ hello|trans(vars) }}', 'Hello Symfony', ['hello' => 'Hello %name%']],
            ['{{ "Hello"|trans({}, "messages", "fr") }}', 'Hello'],

            // trans filter with count
            ['{{ "{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples"|trans(count=count) }}', 'There is 5 apples', ['count' => 5]],
            ['{{ text|trans(count=5, arguments={\'%name%\': \'Symfony\'}) }}', 'There is 5 apples (Symfony)', ['text' => '{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%)']],
            ['{{ "{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples"|trans({}, "messages", "fr", count) }}', 'There is 5 apples', ['count' => 5]],

            // trans filter with null message
            ['{{ null|trans }}', ''],
            ['{{ foo|trans }}', '', ['foo' => null]],

            // trans object
            ['{{ t("")|trans }}', ''],
            ['{{ t("Hello")|trans }}', 'Hello'],
            ['{{ t(name)|trans }}', 'Symfony', ['name' => 'Symfony']],
            ['{{ t(hello, { \'%name%\': \'Symfony\' })|trans }}', 'Hello Symfony', ['hello' => 'Hello %name%']],
            ['{% set vars = { \'%name%\': \'Symfony\' } %}{{ t(hello, vars)|trans }}', 'Hello Symfony', ['hello' => 'Hello %name%']],
            ['{{ t("Hello")|trans("fr") }}', 'Hello'],
            ['{{ t("Hello")|trans(locale="fr") }}', 'Hello'],
            ['{{ t("Hello", {}, "messages")|trans(locale="fr") }}', 'Hello'],

            // trans object with count
            ['{{ t("{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples", {\'%count%\': count})|trans }}', 'There is 5 apples', ['count' => 5]],
        ];
    }

    public function testDefaultTranslationDomain()
    {
        $templates = [
            'index' => '
                {%- extends "base" %}

                {%- trans_default_domain "foo" %}

                {%- block content %}
                    {%- trans %}foo{% endtrans %}
                    {%- trans from "custom" %}foo{% endtrans %}
                    {{- "foo"|trans }}
                    {{- "foo"|trans({}, "custom") }}
                    {{- "foo"|trans(count=1) }}
                    {{- "foo"|trans({"%count%":1}, "custom") }}
                {% endblock %}
            ',

            'base' => '
                {%- block content "" %}
            ',
        ];

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foo (messages)'], 'en');
        $translator->addResource('array', ['foo' => 'foo (custom)'], 'en', 'custom');
        $translator->addResource('array', ['foo' => 'foo (foo)'], 'en', 'foo');

        $template = $this->getTemplate($templates, $translator);

        $this->assertEquals('foo (foo)foo (custom)foo (foo)foo (custom)foo (foo)foo (custom)', trim($template->render([])));
    }

    public function testDefaultTranslationDomainWithNamedArguments()
    {
        $templates = [
            'index' => '
                {%- trans_default_domain "foo" %}

                {%- block content %}
                    {{- "foo"|trans(arguments = {}, domain = "custom") }}
                    {{- "foo"|trans(count = 1) }}
                    {{- "foo"|trans(count = 1, arguments = {}, domain = "custom") }}
                    {{- "foo"|trans({}, domain = "custom") }}
                    {{- "foo"|trans({}, "custom", locale = "fr") }}
                    {{- "foo"|trans(arguments = {"%count%":1}, domain = "custom") }}
                    {{- "foo"|trans({"%count%":1}, "custom", locale = "fr") }}
                {% endblock %}
            ',

            'base' => '
                {%- block content "" %}
            ',
        ];

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foo (messages)'], 'en');
        $translator->addResource('array', ['foo' => 'foo (custom)'], 'en', 'custom');
        $translator->addResource('array', ['foo' => 'foo (foo)'], 'en', 'foo');
        $translator->addResource('array', ['foo' => 'foo (fr)'], 'fr', 'custom');

        $template = $this->getTemplate($templates, $translator);

        $this->assertEquals('foo (custom)foo (foo)foo (custom)foo (custom)foo (fr)foo (custom)foo (fr)', trim($template->render([])));
    }

    private function getTemplate($template, TranslatorInterface $translator = null): TemplateWrapper
    {
        $translator ??= new Translator('en');

        if (\is_array($template)) {
            $loader = new TwigArrayLoader($template);
        } else {
            $loader = new TwigArrayLoader(['index' => $template]);
        }
        $twig = new Environment($loader, ['debug' => true, 'cache' => false]);
        $twig->addExtension(new TranslationExtension($translator));

        return $twig->load('index');
    }
}
