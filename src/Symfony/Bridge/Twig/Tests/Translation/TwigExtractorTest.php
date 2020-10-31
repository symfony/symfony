<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Translation\TwigExtractor;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\LoaderInterface;

class TwigExtractorTest extends TestCase
{
    const VARIABLES_NOTE_CATEGORY = 'symfony-extractor-variables';
    const VARIABLES_NOTE_PREFIX = 'Available variables: ';

    /**
     * @dataProvider getExtractData
     */
    public function testExtract($template, $messages)
    {
        $loader = $this->createMock(LoaderInterface::class);
        $twig = new Environment($loader, [
            'strict_variables' => true,
            'debug' => true,
            'cache' => false,
            'autoescape' => false,
        ]);
        $twig->addExtension(new TranslationExtension($this->createMock(TranslatorInterface::class)));

        $extractor = new TwigExtractor($twig);
        $extractor->setPrefix('prefix');
        $catalogue = new MessageCatalogue('en');

        $m = new \ReflectionMethod($extractor, 'extractTemplate');
        $m->setAccessible(true);
        $m->invoke($extractor, $template, $catalogue);

        if (0 === \count($messages)) {
            $this->assertSame($catalogue->all(), $messages);
        }

        foreach ($messages as $key => $data) {
            $this->assertTrue($catalogue->has($key, $data[0]));
            $this->assertEquals('prefix'.$key, $catalogue->get($key, $data[0]));

            // Check variables
            if ($notes = $catalogue->getMetadata($key, $data[0])['notes'] ?? null) {
                foreach ($notes as $note) {
                    if (isset($note['category']) && self::VARIABLES_NOTE_CATEGORY === $note['category']) {
                        $this->assertEquals(self::VARIABLES_NOTE_PREFIX.$data[1], $note['content']);

                        break;
                    }
                }
            }
        }
    }

    public function getExtractData()
    {
        /*
         *    [
         *        0 => 'TWIG_TEMPLATE',
         *        1 => [
         *            'message_key' => [
         *                0 => 'domain',
         *                1 => 'var1, var2'|null, // Complete message is 'Available variables: var1, var2'
         *                ...
         *            ]
         *        ],
         *        ...
         *    ]
         */
        return [
            ['{{ "new key" | trans() }}', ['new key' => ['messages', null]]],
            ['{{ "new key" | trans() | upper }}', ['new key' => ['messages', null]]],
            ['{{ "new key" | trans({}, "domain") }}', ['new key' => ['domain', null]]],
            ['{% trans %}new key{% endtrans %}', ['new key' => ['messages', null]]],
            ['{% trans %}  new key  {% endtrans %}', ['new key' => ['messages', null]]],
            ['{% trans from "domain" %}new key{% endtrans %}', ['new key' => ['domain', null]]],
            ['{% set foo = "new key" | trans %}', ['new key' => ['messages', null]]],
            ['{{ 1 ? "new key" | trans : "another key" | trans }}', ['new key' => ['messages', null], 'another key' => ['messages', null]]],
            ['{{ t("new key") | trans() }}', ['new key' => ['messages', null]]],
            ['{{ t("new key", {}, "domain") | trans() }}', ['new key' => ['domain', null]]],
            ['{{ 1 ? t("new key") | trans : t("another key") | trans }}', ['new key' => ['messages', null], 'another key' => ['messages', null]]],

            // make sure 'trans_default_domain' tag is supported
            ['{% trans_default_domain "domain" %}{{ "new key"|trans }}', ['new key' => ['domain', null]]],
            ['{% trans_default_domain "domain" %}{% trans %}new key{% endtrans %}', ['new key' => ['domain', null]]],

            // make sure this works with twig's named arguments
            ['{{ "new key" | trans(domain="domain") }}', ['new key' => ['domain', null]]],

            // make sure this works with variables
            // trans tag
            ['{% trans with {\'var1\': \'val1\', \'var2\': \'val2\'} %}trans_tag_with_variables{% endtrans %}', ['trans_tag_with_variables' => ['messages', 'var1, var2']]],
            ['{% trans with {\'var1\': \'val1\', \'var2\': \'val2\'} from \'domain\' %}trans_tag_with_variables_and_domain{% endtrans %}', ['trans_tag_with_variables_and_domain' => ['domain', 'var1, var2']]],
            ['{% trans with {\'var1\': \'val1\', \'var2\': \'val2\'} from \'domain\' into \'en\'%}trans_tag_with_variables_and_domain_and_locale{% endtrans %}', ['trans_tag_with_variables_and_domain_and_locale' => ['domain', 'var1, var2']]],
            ['{% trans_default_domain \'another-domain\' %}{% trans with {\'var1\': \'val1\', \'var2\': \'val2\'} %}trans_tag_with_variables{% endtrans %}', ['trans_tag_with_variables' => ['another-domain', 'var1, var2']]],
            ['{% trans_default_domain \'another-domain\' %}{% trans with {\'var1\': \'val1\', \'var2\': \'val2\'} from \'domain\' %}trans_tag_with_variables_and_domain{% endtrans %}', ['trans_tag_with_variables_and_domain' => ['domain', 'var1, var2']]],
            ['{% trans_default_domain \'another-domain\' %}{% trans with {\'var1\': \'val1\', \'var2\': \'val2\'} from \'domain\' into \'en\'%}trans_tag_with_variables_and_domain_and_locale{% endtrans %}', ['trans_tag_with_variables_and_domain_and_locale' => ['domain', 'var1, var2']]],

            // |trans() filter
            ['{{ \'trans_filter_with_variable_as_param\'|trans({\'var1\': \'val1\', \'var2\': \'val2\'}) }}', ['trans_filter_with_variable_as_param' => ['messages', 'var1, var2']]],
            ['{{ \'trans_filter_with_variable_as_param\'|trans({\'var1\': \'val1\', \'var2\': \'val2\'}, \'domain\') }}', ['trans_filter_with_variable_as_param' => ['domain', 'var1, var2']]],
            ['{% trans_default_domain \'another-domain\' %}{{ \'trans_filter_with_variable_as_param\'|trans({\'var1\': \'val1\', \'var2\': \'val2\'}) }}', ['trans_filter_with_variable_as_param' => ['another-domain', 'var1, var2']]],
            ['{% trans_default_domain \'another-domain\' %}{{ \'trans_filter_with_variable_as_param\'|trans({\'var1\': \'val1\', \'var2\': \'val2\'}, \'domain\') }}', ['trans_filter_with_variable_as_param' => ['domain', 'var1, var2']]],

            // t() function with |trans() filter
            // Be careful: the t() function creates a TranslatableMessage object which overrides the domain set with 'trans_default_domain' (no domain specified = 'messages').
            ['{{ t(\'t_function_with_variables_with_trans_filter\', {\'var1\': \'val1\', \'var2\': \'val2\'})|trans }}', ['t_function_with_variables_with_trans_filter' => ['messages', 'var1, var2']]],
            ['{{ t(\'t_function_with_variables_and_domain_with_trans_filter\', {\'var1\': \'val1\', \'var2\': \'val2\'}, \'domain\')|trans }}', ['t_function_with_variables_and_domain_with_trans_filter' => ['domain', 'var1, var2']]],
            ['{{ t(\'t_function_with_variables_with_trans_filter_and_locale\', {\'var1\': \'val1\', \'var2\': \'val2\'})|trans(\'en\') }}', ['t_function_with_variables_with_trans_filter_and_locale' => ['messages', 'var1, var2']]],
            ['{{ t(\'t_function_with_variables_and_domain_with_trans_filter_and_locale\', {\'var1\': \'val1\', \'var2\': \'val2\'}, \'domain\')|trans(\'en\') }}', ['t_function_with_variables_and_domain_with_trans_filter_and_locale' => ['domain', 'var1, var2']]],
            ['{% trans_default_domain \'another-domain\' %}{{    t(\'t_function_with_variables_with_trans_filter\', {\'var1\': \'val1\', \'var2\': \'val2\'})|trans }}', ['t_function_with_variables_with_trans_filter' => ['messages', 'var1, var2']]], // Be careful! (read note above)
            ['{% trans_default_domain \'another-domain\' %}{{ t(\'t_function_with_variables_and_domain_with_trans_filter\', {\'var1\': \'val1\', \'var2\': \'val2\'}, \'domain\')|trans }}', ['t_function_with_variables_and_domain_with_trans_filter' => ['domain', 'var1, var2']]],
            ['{% trans_default_domain \'another-domain\' %}{{ t(\'t_function_with_variables_with_trans_filter_and_locale\', {\'var1\': \'val1\', \'var2\': \'val2\'})|trans(\'en\') }}', ['t_function_with_variables_with_trans_filter_and_locale' => ['messages', 'var1, var2']]], // Be careful! (read note above)
            ['{% trans_default_domain \'another-domain\' %}{{ t(\'t_function_with_variables_and_domain_with_trans_filter_and_locale\', {\'var1\': \'val1\', \'var2\': \'val2\'}, \'domain\')|trans(\'en\') }}', ['t_function_with_variables_and_domain_with_trans_filter_and_locale' => ['domain', 'var1, var2']]],

            // t() function alone (e.g. as a variable value)
            // Be careful: the t() function creates a TranslatableMessage object which overrides the domain set with 'trans_default_domain' (no domain specified = 'messages').
            ['{% set t_function_in_a_variable =  t(\'t_function_in_a_variable\') %}{{ t_function_in_a_variable|trans }}', ['t_function_in_a_variable' => ['messages', null]]],
            ['{% set t_function_with_variables_in_a_variable =  t(\'t_function_with_variables_in_a_variable\', {\'var1\': \'val1\', \'var2\': \'val2\'}) %}{{ t_function_with_variables_in_a_variable|trans }}', ['t_function_with_variables_in_a_variable' => ['messages', 'var1, var2']]],
            ['{% set t_function_with_variables_and_domain_in_a_variable =  t(\'t_function_with_variables_and_domain_in_a_variable\', {\'var1\': \'val1\', \'var2\': \'val2\'}, \'domain\') %}{{ t_function_with_variables_and_domain_in_a_variable|trans }}', ['t_function_with_variables_and_domain_in_a_variable' => ['domain', 'var1, var2']]],
            ['{% trans_default_domain \'another-domain\' %}{% set t_function_in_a_variable =  t(\'t_function_in_a_variable\') %}{{ t_function_in_a_variable|trans }}', ['t_function_in_a_variable' => ['messages', null]]],
            ['{% trans_default_domain \'another-domain\' %}{% set t_function_with_variables_in_a_variable =  t(\'t_function_with_variables_in_a_variable\', {\'var1\': \'val1\', \'var2\': \'val2\'}) %}{{ t_function_with_variables_in_a_variable|trans }}', ['t_function_with_variables_in_a_variable' => ['messages', 'var1, var2']]],
            ['{% trans_default_domain \'another-domain\' %}{% set t_function_with_variables_and_domain_in_a_variable =  t(\'t_function_with_variables_and_domain_in_a_variable\', {\'var1\': \'val1\', \'var2\': \'val2\'}, \'domain\') %}{{ t_function_with_variables_and_domain_in_a_variable|trans }}', ['t_function_with_variables_and_domain_in_a_variable' => ['domain', 'var1, var2']]],

            // concat translations
            ['{{ ("new" ~ " key") | trans() }}', ['new key' => ['messages', null]]],
            ['{{ ("another " ~ "new " ~ "key") | trans() }}', ['another new key' => ['messages', null]]],
            ['{{ ("new" ~ " key") | trans(domain="domain") }}', ['new key' => ['domain', null]]],
            ['{{ ("another " ~ "new " ~ "key") | trans(domain="domain") }}', ['another new key' => ['domain', null]]],
            // if it has a variable or other expression, we can not extract it
            ['{% set foo = "new" %} {{ ("new " ~ foo ~ "key") | trans() }}', []],
            ['{{ ("foo " ~ "new"|trans ~ "key") | trans() }}', ['new' => ['messages', null]]],
        ];
    }

    /**
     * @dataProvider resourcesWithSyntaxErrorsProvider
     */
    public function testExtractSyntaxError($resources, array $messages)
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        $twig->addExtension(new TranslationExtension($this->createMock(TranslatorInterface::class)));

        $extractor = new TwigExtractor($twig);
        $catalogue = new MessageCatalogue('en');
        $extractor->extract($resources, $catalogue);
        $this->assertSame($messages, $catalogue->all());
    }

    public function resourcesWithSyntaxErrorsProvider(): array
    {
        return [
            [__DIR__.'/../Fixtures', ['messages' => ['Hi!' => 'Hi!']]],
            [__DIR__.'/../Fixtures/extractor/syntax_error.twig', []],
            [new \SplFileInfo(__DIR__.'/../Fixtures/extractor/syntax_error.twig'), []],
        ];
    }

    /**
     * @dataProvider resourceProvider
     */
    public function testExtractWithFiles($resource)
    {
        $loader = new ArrayLoader([]);
        $twig = new Environment($loader, [
            'strict_variables' => true,
            'debug' => true,
            'cache' => false,
            'autoescape' => false,
        ]);
        $twig->addExtension(new TranslationExtension($this->createMock(TranslatorInterface::class)));

        $extractor = new TwigExtractor($twig);
        $catalogue = new MessageCatalogue('en');
        $extractor->extract($resource, $catalogue);

        $this->assertTrue($catalogue->has('Hi!', 'messages'));
        $this->assertEquals('Hi!', $catalogue->get('Hi!', 'messages'));
    }

    public function resourceProvider(): array
    {
        $directory = __DIR__.'/../Fixtures/extractor/';

        return [
            [$directory.'with_translations.html.twig'],
            [[$directory.'with_translations.html.twig']],
            [[new \SplFileInfo($directory.'with_translations.html.twig')]],
            [new \ArrayObject([$directory.'with_translations.html.twig'])],
            [new \ArrayObject([new \SplFileInfo($directory.'with_translations.html.twig')])],
        ];
    }
}
