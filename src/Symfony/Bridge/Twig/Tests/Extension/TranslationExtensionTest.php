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
use Twig\Environment;
use Twig\Loader\ArrayLoader as TwigArrayLoader;

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

    /**
     * @group legacy
     * @dataProvider getTransChoiceTests
     */
    public function testTransChoice($template, $expected, array $variables = [])
    {
        $this->testTrans($template, $expected, $variables);
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
     * @group legacy
     * @expectedException        \Twig\Error\SyntaxError
     * @expectedExceptionMessage A message inside a transchoice tag must be a simple text in "index" at line 2.
     */
    public function testTransChoiceComplexBody()
    {
        $output = $this->getTemplate("{% transchoice count %}\n{{ 1 + 2 }}{% endtranschoice %}")->render();
    }

    public function getTransTests()
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
        ];
    }

    /**
     * @group legacy
     */
    public function getTransChoiceTests()
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

            // transchoice
            [
                '{% transchoice count from "messages" %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is no apples',
                ['count' => 0],
            ],
            [
                '{% transchoice count %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is 5 apples',
                ['count' => 5],
            ],
            [
                '{% transchoice count %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtranschoice %}',
                'There is 5 apples (Symfony)',
                ['count' => 5, 'name' => 'Symfony'],
            ],
            [
                '{% transchoice count with { \'%name%\': \'Symfony\' } %}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%){% endtranschoice %}',
                'There is 5 apples (Symfony)',
                ['count' => 5],
            ],
            [
                '{% transchoice count into "fr"%}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is no apples',
                ['count' => 0],
            ],
            [
                '{% transchoice 5 into "fr"%}{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples{% endtranschoice %}',
                'There is 5 apples',
            ],

            // trans filter
            ['{{ "Hello"|trans }}', 'Hello'],
            ['{{ name|trans }}', 'Symfony', ['name' => 'Symfony']],
            ['{{ hello|trans({ \'%name%\': \'Symfony\' }) }}', 'Hello Symfony', ['hello' => 'Hello %name%']],
            ['{% set vars = { \'%name%\': \'Symfony\' } %}{{ hello|trans(vars) }}', 'Hello Symfony', ['hello' => 'Hello %name%']],
            ['{{ "Hello"|trans({}, "messages", "fr") }}', 'Hello'],

            // transchoice filter
            ['{{ "{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples"|transchoice(count) }}', 'There is 5 apples', ['count' => 5]],
            ['{{ text|transchoice(5, {\'%name%\': \'Symfony\'}) }}', 'There is 5 apples (Symfony)', ['text' => '{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples (%name%)']],
            ['{{ "{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples"|transchoice(count, {}, "messages", "fr") }}', 'There is 5 apples', ['count' => 5]],
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

    protected function getTemplate($template, $translator = null)
    {
        if (null === $translator) {
            $translator = new Translator('en');
        }

        if (\is_array($template)) {
            $loader = new TwigArrayLoader($template);
        } else {
            $loader = new TwigArrayLoader(['index' => $template]);
        }
        $twig = new Environment($loader, ['debug' => true, 'cache' => false]);
        $twig->addExtension(new TranslationExtension($translator));

        return $twig->loadTemplate('index');
    }
}
