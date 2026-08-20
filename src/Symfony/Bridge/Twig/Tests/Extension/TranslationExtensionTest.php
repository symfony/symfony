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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader as TwigArrayLoader;
use Twig\TemplateWrapper;

class TranslationExtensionTest extends TestCase
{
    public function testEscaping()
    {
        $output = $this->getTemplate('{% trans %}Percent: %value%%% (%msg%){% endtrans %}')->render(['value' => 12, 'msg' => 'approx.']);

        $this->assertEquals('Percent: 12% (approx.)', $output);
    }

    #[DataProvider('getTransTests')]
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
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unexpected token. Twig was looking for the "with", "from", or "into" keyword in "index" at line 3.');
        $this->getTemplate("{% trans \n\nfoo %}{% endtrans %}")->render();
    }

    public function testTransComplexBody()
    {
        $this->expectException(SyntaxError::class);
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

    public function testDefaultTranslationDomainWithExpression()
    {
        $templates = [
            'index' => '
                {%- extends "base" %}

                {%- trans_default_domain custom_domain %}

                {%- block content %}
                    {{- "foo"|trans }}
                {%- endblock %}
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

        $this->assertEquals('foo (foo)', trim($template->render(['custom_domain' => 'foo'])));
    }

    public function testDefaultTranslationDomainWithExpressionAndInheritance()
    {
        $templates = [
            'index' => '
                {%- extends "base" %}

                {%- trans_default_domain foo_domain %}

                {%- block content %}
                    {{- "foo"|trans }}
                {%- endblock %}
            ',

            'base' => '
                {%- trans_default_domain custom_domain %}

                {{- "foo"|trans }}
                {%- block content "" %}
                {{- "foo"|trans }}
            ',
        ];

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foo (messages)'], 'en');
        $translator->addResource('array', ['foo' => 'foo (custom)'], 'en', 'custom');
        $translator->addResource('array', ['foo' => 'foo (foo)'], 'en', 'foo');

        $template = $this->getTemplate($templates, $translator);

        $this->assertEquals('foo (custom)foo (foo)foo (custom)', trim($template->render(['foo_domain' => 'foo', 'custom_domain' => 'custom'])));
    }

    public function testForSelfDefaultTranslationDomain()
    {
        $templates = [
            'index' => '
                {%- trans_default_domain "foo" for _self -%}
                1. {{ "key"|trans -}}{{" "-}}
                2. {% trans %}key{% endtrans %}{{" "-}}
                {%- embed "embedded.html.twig" -%}
                    {%- block content -%}
                    3. {{ "key"|trans }}{{" "-}}
                    {%- endblock -%}
                {%- endembed %}
            ',

            // another template, with its own "for _self" that applies to its own trans calls only
            'embedded.html.twig' => '
                {%- trans_default_domain "bar" for _self -%}
                {%- block content "" -%}
                4. {{ "key"|trans -}}
            ',
        ];

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['key' => 'key-messages'], 'en');
        $translator->addResource('array', ['key' => 'key-foo'], 'en', 'foo');
        $translator->addResource('array', ['key' => 'key-bar'], 'en', 'bar');

        $template = $this->getTemplate($templates, $translator);

        $this->assertEquals('1. key-foo 2. key-foo 3. key-foo 4. key-bar', trim($template->render([])));
    }

    public function testForSelfDefaultTranslationDomainWithNestedEmbed()
    {
        $templates = [
            'index' => '
                {%- trans_default_domain "foo" for _self -%}
                {%- embed "outer_embed.html.twig" -%}
                    {%- block content -%}
                        1. {{ "key"|trans -}}{{" "-}}
                        {%- embed "inner_embed.html.twig" -%}
                            {%- block inner -%}
                            2. {{ "key"|trans }}{{" "-}}
                            {%- endblock -%}
                        {%- endembed -%}
                    {%- endblock -%}
                {%- endembed -%}
            ',

            'outer_embed.html.twig' => '
                {%- block content "" -%}
            ',

            // the trans call below is written in another template and must not be affected
            'inner_embed.html.twig' => '
                {%- block inner "" -%}
                3. {{ "key"|trans -}}
            ',
        ];

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['key' => 'key-messages'], 'en');
        $translator->addResource('array', ['key' => 'key-foo'], 'en', 'foo');

        $template = $this->getTemplate($templates, $translator);

        $this->assertEquals('1. key-foo 2. key-foo 3. key-messages', trim($template->render([])));
    }

    public function testForSelfDefaultTranslationDomainAppliesToMacros()
    {
        $template = '
            {%- trans_default_domain "foo" for _self -%}
            {{- _self.one() }} {{ _self.two() -}}
            {%- macro one() -%}
                1. {{ "key"|trans -}}
            {%- endmacro -%}
            {%- macro two() -%}
                2. {{ "key"|trans -}}
            {%- endmacro -%}
        ';

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['key' => 'key-messages'], 'en');
        $translator->addResource('array', ['key' => 'key-foo'], 'en', 'foo');

        $this->assertEquals('1. key-foo 2. key-foo', trim($this->getTemplate($template, $translator)->render([])));
    }

    public function testForSelfDefaultTranslationDomainAppliesToInheritedBlocks()
    {
        $templates = [
            'index' => '
                {%- extends "layout.html.twig" -%}
                {%- trans_default_domain "foo" for _self -%}
                {%- block content -%}{{ "key"|trans }}{%- endblock -%}
            ',
            'layout.html.twig' => '{% block content "" %}',
        ];

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['key' => 'key-messages'], 'en');
        $translator->addResource('array', ['key' => 'key-foo'], 'en', 'foo');

        $this->assertEquals('key-foo', trim($this->getTemplate($templates, $translator)->render([])));
    }

    public function testForSelfDefaultTranslationDomainDoesNotLeakIntoIncludedTemplates()
    {
        $templates = [
            'index' => '{% trans_default_domain "foo" for _self %}{% include "included.html.twig" %}',
            'included.html.twig' => '{{ "key"|trans }}',
        ];

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['key' => 'key-messages'], 'en');
        $translator->addResource('array', ['key' => 'key-foo'], 'en', 'foo');

        $this->assertEquals('key-messages', trim($this->getTemplate($templates, $translator)->render([])));
    }

    public function testForSelfDefaultTranslationDomainPrecedence()
    {
        $template = '
            {%- trans_default_domain "foo" for _self -%}
            1. {{ "key"|trans -}}{{" "-}}
            2. {{ "key"|trans({}, "explicit") -}}{{" "-}}
            {%- trans_default_domain "bar" -%}
            3. {{ "key"|trans -}}{{" "-}}
            4. {{ "key"|trans({}, "explicit") -}}{{" "-}}
            {%- trans_default_domain "baz" -%}
            5. {{ "key"|trans -}}{{" "-}}
            6. {{ "key"|trans({}, "explicit") -}}
        ';

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['key' => 'key-messages'], 'en');
        $translator->addResource('array', ['key' => 'key-foo'], 'en', 'foo');
        $translator->addResource('array', ['key' => 'key-bar'], 'en', 'bar');
        $translator->addResource('array', ['key' => 'key-baz'], 'en', 'baz');
        $translator->addResource('array', ['key' => 'key-explicit'], 'en', 'explicit');

        // a scoped trans_default_domain wins over "for _self", an explicit domain wins over both
        $this->assertEquals('1. key-foo 2. key-explicit 3. key-bar 4. key-explicit 5. key-baz 6. key-explicit', trim($this->getTemplate($template, $translator)->render([])));
    }

    public function testForSelfDefaultTranslationDomainCanBeOverriddenInsideAnEmbed()
    {
        $templates = [
            'index' => '
                {%- trans_default_domain "foo" for _self -%}
                {%- embed "embedded.html.twig" -%}
                    {%- block content -%}
                        {%- trans_default_domain "bar" -%}
                        {{- "key"|trans -}}
                    {%- endblock -%}
                {%- endembed -%}
            ',
            'embedded.html.twig' => '{% block content "" %}',
        ];

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['key' => 'key-foo'], 'en', 'foo');
        $translator->addResource('array', ['key' => 'key-bar'], 'en', 'bar');

        $this->assertEquals('key-bar', trim($this->getTemplate($templates, $translator)->render([])));
    }

    public function testForSelfDefaultTranslationDomainAppliesToCallsBeforeDeclaration()
    {
        $template = '
            1. {{ "key"|trans -}}{{" "-}}
            {%- trans_default_domain "foo" for _self -%}
            2. {{ "key"|trans -}}
        ';

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['key' => 'key-messages'], 'en');
        $translator->addResource('array', ['key' => 'key-foo'], 'en', 'foo');

        $this->assertEquals('1. key-foo 2. key-foo', trim($this->getTemplate($template, $translator)->render([])));
    }

    public function testForSelfDefaultTranslationDomainIsExtracted()
    {
        $templates = [
            'index' => '
                {%- trans_default_domain "foo" for _self -%}
                {{ "outer"|trans }}
                {%- embed "embedded.html.twig" -%}
                    {%- block content -%}{{ "embedded"|trans }}{%- endblock -%}
                {%- endembed -%}
            ',
            'embedded.html.twig' => '{% block content "" %}',
        ];

        $twig = new Environment(new TwigArrayLoader($templates), ['debug' => true, 'cache' => false]);
        $twig->addExtension($extension = new TranslationExtension(new Translator('en')));

        $visitor = $extension->getTranslationNodeVisitor();
        $visitor->enable();
        $twig->parse($twig->tokenize($twig->getLoader()->getSourceContext('index')));

        $this->assertEquals([['embedded', 'foo'], ['outer', 'foo']], $visitor->getMessages());
    }

    public function testForSelfWithNonConstantDomainThrows()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The "for _self" modifier of the "trans_default_domain" tag requires a constant domain');
        $this->getTemplate('{% trans_default_domain domain for _self %}');
    }

    public function testForSelfInsideBlockThrows()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The "trans_default_domain" tag cannot be used with "for _self" inside a block');
        $this->getTemplate('{% block content %}{% trans_default_domain "foo" for _self %}{% endblock %}');
    }

    public function testForSelfDeclaredTwiceThrows()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The "trans_default_domain" tag can be used with "for _self" only once per template');
        $this->getTemplate('{% trans_default_domain "foo" for _self %}{% trans_default_domain "bar" for _self %}');
    }

    public function testAFailingTemplateDoesNotLeakItsDomainIntoTheNextOne()
    {
        $translator = new Translator('en');
        $loader = new TwigArrayLoader([
            'broken' => '{% block content %}{% trans_default_domain "foo" for _self %}{% endblock %}',
            'other' => '{{ "key"|trans }}',
        ]);
        $twig = new Environment($loader, ['debug' => true, 'cache' => false]);
        $twig->addExtension(new TranslationExtension($translator));

        try {
            $twig->load('broken');
            $this->fail('The template is expected to fail.');
        } catch (SyntaxError) {
        }

        $source = $twig->compile($twig->parse($twig->tokenize($twig->getLoader()->getSourceContext('other'))));

        $this->assertStringNotContainsString('foo', $source);
    }

    public function testForSelfInsideEmbedThrows()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The "trans_default_domain" tag cannot be used with "for _self" inside an "embed" tag');
        $this->getTemplate([
            'index' => '{% embed "layout.html.twig" %}{% trans_default_domain "foo" for _self %}{% block content %}{% endblock %}{% endembed %}',
            'layout.html.twig' => '{% block content "" %}',
        ]);
    }

    public function testForSelfAfterEmbedThrows()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The "trans_default_domain" tag must be used with "for _self" before any "embed" tag');
        $this->getTemplate([
            'index' => '{% embed "layout.html.twig" %}{% block content %}{% endblock %}{% endembed %}{% trans_default_domain "foo" for _self %}',
            'layout.html.twig' => '{% block content "" %}',
        ]);
    }

    private function getTemplate($template, ?TranslatorInterface $translator = null): TemplateWrapper
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
