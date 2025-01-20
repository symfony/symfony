<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Exception\RuntimeException;
use Symfony\Component\Translation\Formatter\IntlFormatterInterface;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;

class TranslatorTest extends TestCase
{
    private string $defaultLocale;

    protected function setUp(): void
    {
        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('en');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->defaultLocale);
    }

    /**
     * @dataProvider getInvalidLocalesTests
     */
    public function testConstructorInvalidLocale($locale)
    {
        $this->expectException(InvalidArgumentException::class);
        new Translator($locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testConstructorValidLocale($locale)
    {
        $translator = new Translator($locale);

        $this->assertSame($locale ?: (class_exists(\Locale::class) ? \Locale::getDefault() : 'en'), $translator->getLocale());
    }

    public function testSetGetLocale()
    {
        $translator = new Translator('en');

        $this->assertEquals('en', $translator->getLocale());

        $translator->setLocale('fr');
        $this->assertEquals('fr', $translator->getLocale());
    }

    /**
     * @dataProvider getInvalidLocalesTests
     */
    public function testSetInvalidLocale(string $locale)
    {
        $translator = new Translator('fr');

        $this->expectException(InvalidArgumentException::class);

        $translator->setLocale($locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testSetValidLocale(string $locale)
    {
        $translator = new Translator($locale);
        $translator->setLocale($locale);

        $this->assertEquals($locale ?: (class_exists(\Locale::class) ? \Locale::getDefault() : 'en'), $translator->getLocale());
    }

    public function testGetCatalogue()
    {
        $translator = new Translator('en');

        $this->assertEquals(new MessageCatalogue('en'), $translator->getCatalogue());

        $translator->setLocale('fr');
        $this->assertEquals(new MessageCatalogue('fr'), $translator->getCatalogue('fr'));
    }

    public function testGetCatalogueReturnsConsolidatedCatalogue()
    {
        /*
         * This will be useful once we refactor so that different domains will be loaded lazily (on-demand).
         * In that case, getCatalogue() will probably have to load all missing domains in order to return
         * one complete catalogue.
         */

        $locale = 'whatever';
        $translator = new Translator($locale);
        $translator->addLoader('loader-a', new ArrayLoader());
        $translator->addLoader('loader-b', new ArrayLoader());
        $translator->addResource('loader-a', ['foo' => 'foofoo'], $locale, 'domain-a');
        $translator->addResource('loader-b', ['bar' => 'foobar'], $locale, 'domain-b');

        /*
         * Test that we get a single catalogue comprising messages
         * from different loaders and different domains
         */
        $catalogue = $translator->getCatalogue($locale);
        $this->assertTrue($catalogue->defines('foo', 'domain-a'));
        $this->assertTrue($catalogue->defines('bar', 'domain-b'));
    }

    public function testSetFallbackLocales()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en');
        $translator->addResource('array', ['bar' => 'foobar'], 'fr');

        // force catalogue loading
        $translator->trans('bar');

        $translator->setFallbackLocales(['fr']);
        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    public function testSetFallbackLocalesMultiple()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foo (en)'], 'en');
        $translator->addResource('array', ['bar' => 'bar (fr)'], 'fr');

        // force catalogue loading
        $translator->trans('bar');

        $translator->setFallbackLocales(['fr_FR', 'fr']);
        $this->assertEquals('bar (fr)', $translator->trans('bar'));
    }

    /**
     * @dataProvider getInvalidLocalesTests
     */
    public function testSetFallbackInvalidLocales($locale)
    {
        $this->expectException(InvalidArgumentException::class);
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['fr', $locale]);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testSetFallbackValidLocales($locale)
    {
        $translator = new Translator($locale);
        $translator->setFallbackLocales(['fr', $locale]);
        // no assertion. this method just asserts that no exception is thrown
        $this->addToAssertionCount(1);
    }

    public function testTransWithFallbackLocale()
    {
        $translator = new Translator('fr_FR');
        $translator->setFallbackLocales(['en']);

        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['bar' => 'foobar'], 'en');

        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    /**
     * @dataProvider getInvalidLocalesTests
     */
    public function testAddResourceInvalidLocales($locale)
    {
        $translator = new Translator('fr');

        $this->expectException(InvalidArgumentException::class);

        $translator->addResource('array', ['foo' => 'foofoo'], $locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testAddResourceValidLocales(string $locale)
    {
        $translator = new Translator('fr');
        $translator->addResource('array', ['foo' => 'foofoo'], $locale);
        // no assertion. this method just asserts that no exception is thrown
        $this->addToAssertionCount(1);
    }

    public function testAddResourceAfterTrans()
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());

        $translator->setFallbackLocales(['en']);

        $translator->addResource('array', ['foo' => 'foofoo'], 'en');
        $this->assertEquals('foofoo', $translator->trans('foo'));

        $translator->addResource('array', ['bar' => 'foobar'], 'en');
        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    /**
     * @dataProvider getTransFileTests
     */
    public function testTransWithoutFallbackLocaleFile(string $format, string $loader)
    {
        $loaderClass = 'Symfony\\Component\\Translation\\Loader\\'.$loader;
        $translator = new Translator('en');
        $translator->addLoader($format, new $loaderClass());
        $translator->addResource($format, __DIR__.'/Fixtures/non-existing', 'en');
        $translator->addResource($format, __DIR__.'/Fixtures/resources.'.$format, 'en');

        $this->expectException(NotFoundResourceException::class);

        // force catalogue loading
        $translator->trans('foo');
    }

    /**
     * @dataProvider getTransFileTests
     */
    public function testTransWithFallbackLocaleFile(string $format, string $loader)
    {
        $loaderClass = 'Symfony\\Component\\Translation\\Loader\\'.$loader;
        $translator = new Translator('en_GB');
        $translator->addLoader($format, new $loaderClass());
        $translator->addResource($format, __DIR__.'/Fixtures/non-existing', 'en_GB');
        $translator->addResource($format, __DIR__.'/Fixtures/resources.'.$format, 'en', 'resources');

        $this->assertEquals('bar', $translator->trans('foo', [], 'resources'));
    }

    public function testTransWithIcuFallbackLocale()
    {
        $translator = new Translator('en_GB');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en_GB');
        $translator->addResource('array', ['bar' => 'foobar'], 'en_001');
        $translator->addResource('array', ['baz' => 'foobaz'], 'en');
        $this->assertSame('foofoo', $translator->trans('foo'));
        $this->assertSame('foobar', $translator->trans('bar'));
        $this->assertSame('foobaz', $translator->trans('baz'));
    }

    public function testTransWithIcuVariantFallbackLocale()
    {
        $translator = new Translator('en_GB_scouse');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en_GB_scouse');
        $translator->addResource('array', ['bar' => 'foobar'], 'en_GB');
        $translator->addResource('array', ['baz' => 'foobaz'], 'en_001');
        $translator->addResource('array', ['bar' => 'en', 'qux' => 'fooqux'], 'en');
        $translator->addResource('array', ['bar' => 'nl_NL', 'fallback' => 'nl_NL'], 'nl_NL');
        $translator->addResource('array', ['bar' => 'nl', 'fallback' => 'nl'], 'nl');

        $translator->setFallbackLocales(['nl_NL', 'nl']);

        $this->assertSame('foofoo', $translator->trans('foo'));
        $this->assertSame('foobar', $translator->trans('bar'));
        $this->assertSame('foobaz', $translator->trans('baz'));
        $this->assertSame('fooqux', $translator->trans('qux'));
        $this->assertSame('nl_NL', $translator->trans('fallback'));
    }

    public function testTransWithIcuRootFallbackLocale()
    {
        $translator = new Translator('az_Cyrl');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'az_Cyrl');
        $translator->addResource('array', ['bar' => 'foobar'], 'az');
        $this->assertSame('foofoo', $translator->trans('foo'));
        $this->assertSame('bar', $translator->trans('bar'));
    }

    /**
     * @dataProvider getFallbackLocales
     */
    public function testTransWithFallbackLocaleBis($expectedLocale, $locale)
    {
        $translator = new Translator($locale);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], $locale);
        $translator->addResource('array', ['bar' => 'foobar'], $expectedLocale);
        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    public static function getFallbackLocales()
    {
        $locales = [
            ['en', 'en_US'],
            ['en', 'en-US'],
            ['sl_Latn_IT', 'sl_Latn_IT_nedis'],
            ['sl_Latn', 'sl_Latn_IT'],
        ];

        if (\function_exists('locale_parse')) {
            $locales[] = ['sl_Latn_IT', 'sl-Latn-IT-nedis'];
            $locales[] = ['sl_Latn', 'sl-Latn-IT'];
        } else {
            $locales[] = ['sl-Latn-IT', 'sl-Latn-IT-nedis'];
            $locales[] = ['sl-Latn', 'sl-Latn-IT'];
        }

        return $locales;
    }

    public function testTransWithFallbackLocaleTer()
    {
        $translator = new Translator('fr_FR');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foo (en_US)'], 'en_US');
        $translator->addResource('array', ['foo' => 'foo (en)', 'bar' => 'bar (en)'], 'en');

        $translator->setFallbackLocales(['en_US', 'en']);

        $this->assertEquals('foo (en_US)', $translator->trans('foo'));
        $this->assertEquals('bar (en)', $translator->trans('bar'));
    }

    public function testTransNonExistentWithFallback()
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('array', new ArrayLoader());
        $this->assertEquals('non-existent', $translator->trans('non-existent'));
    }

    public function testWhenAResourceHasNoRegisteredLoader()
    {
        $translator = new Translator('en');
        $translator->addResource('array', ['foo' => 'foofoo'], 'en');

        $this->expectException(RuntimeException::class);

        $translator->trans('foo');
    }

    public function testNestedFallbackCatalogueWhenUsingMultipleLocales()
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['ru', 'en']);

        $translator->getCatalogue('fr');

        $this->assertNotNull($translator->getCatalogue('ru')->getFallbackCatalogue());
    }

    public function testFallbackCatalogueResources()
    {
        $translator = new Translator('en_GB');
        $translator->addLoader('yml', new \Symfony\Component\Translation\Loader\YamlFileLoader());
        $translator->addResource('yml', __DIR__.'/Fixtures/empty.yml', 'en_GB');
        $translator->addResource('yml', __DIR__.'/Fixtures/resources.yml', 'en');

        // force catalogue loading
        $this->assertEquals('bar', $translator->trans('foo', []));

        $resources = $translator->getCatalogue('en')->getResources();
        $this->assertCount(1, $resources);
        $this->assertContainsEquals(__DIR__.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR.'resources.yml', $resources);

        $resources = $translator->getCatalogue('en_GB')->getResources();
        $this->assertCount(2, $resources);
        $this->assertContainsEquals(__DIR__.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR.'empty.yml', $resources);
        $this->assertContainsEquals(__DIR__.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR.'resources.yml', $resources);
    }

    /**
     * @dataProvider getTransTests
     */
    public function testTrans($expected, $id, $translation, $parameters, $locale, $domain)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [(string) $id => $translation], $locale, $domain);

        $this->assertEquals($expected, $translator->trans($id, $parameters, $domain, $locale));
    }

    /**
     * @dataProvider getTransICUTests
     */
    public function testTransICU(...$args)
    {
        if (!class_exists(\MessageFormatter::class)) {
            $this->markTestSkipped(\sprintf('Skipping test as the required "%s" class does not exist. Consider installing the "intl" PHP extension or the "symfony/polyfill-intl-messageformatter" package.', \MessageFormatter::class));
        }

        $this->testTrans(...$args);
    }

    /**
     * @dataProvider getInvalidLocalesTests
     */
    public function testTransInvalidLocale($locale)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en');

        $this->expectException(InvalidArgumentException::class);

        $translator->trans('foo', [], '', $locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testTransValidLocale(string $locale)
    {
        $translator = new Translator($locale);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['test' => 'OK'], $locale);

        $this->assertEquals('OK', $translator->trans('test'));
        $this->assertEquals('OK', $translator->trans('test', [], null, $locale));
    }

    /**
     * @dataProvider getFlattenedTransTests
     */
    public function testFlattenedTrans(string $expected, $messages, $id)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $messages, 'fr', '');

        $this->assertEquals($expected, $translator->trans($id, [], '', 'fr'));
    }

    public function testTransNullId()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en');

        $this->assertSame('', $translator->trans(null));

        (\Closure::bind(function () use ($translator) {
            $this->assertSame([], $translator->catalogues);
        }, $this, Translator::class))();
    }

    public static function getTransFileTests()
    {
        return [
            ['csv', 'CsvFileLoader'],
            ['ini', 'IniFileLoader'],
            ['mo', 'MoFileLoader'],
            ['po', 'PoFileLoader'],
            ['php', 'PhpFileLoader'],
            ['ts', 'QtFileLoader'],
            ['xlf', 'XliffFileLoader'],
            ['yml', 'YamlFileLoader'],
            ['json', 'JsonFileLoader'],
        ];
    }

    public static function getTransTests(): array
    {
        $param = new TranslatableMessage('Symfony is %what%!', ['%what%' => 'awesome'], '');

        return [
            ['Symfony est super !', 'Symfony is great!', 'Symfony est super !', [], 'fr', ''],
            ['Symfony est awesome !', 'Symfony is %what%!', 'Symfony est %what% !', ['%what%' => 'awesome'], 'fr', ''],
            ['Symfony est Symfony est awesome ! !', 'Symfony is %what%!', 'Symfony est %what% !', ['%what%' => $param], 'fr', ''],
            ['Symfony est super !', new StringClass('Symfony is great!'), 'Symfony est super !', [], 'fr', ''],
            ['', null, '', [], 'fr', ''],
        ];
    }

    public static function getTransICUTests()
    {
        $id = '{apples, plural, =0 {There are no apples} one {There is one apple} other {There are # apples}}';

        return [
            ['There are no apples', $id, $id, ['{apples}' => 0], 'en', 'test'.MessageCatalogue::INTL_DOMAIN_SUFFIX],
            ['There is one apple',  $id, $id, ['{apples}' => 1], 'en', 'test'.MessageCatalogue::INTL_DOMAIN_SUFFIX],
            ['There are 3 apples',  $id, $id, ['{apples}' => 3], 'en', 'test'.MessageCatalogue::INTL_DOMAIN_SUFFIX],
        ];
    }

    public static function getFlattenedTransTests()
    {
        $messages = [
            'symfony' => [
                'is' => [
                    'great' => 'Symfony est super!',
                ],
            ],
            'foo' => [
                'bar' => [
                    'baz' => 'Foo Bar Baz',
                ],
                'baz' => 'Foo Baz',
            ],
        ];

        return [
            ['Symfony est super!', $messages, 'symfony.is.great'],
            ['Foo Bar Baz', $messages, 'foo.bar.baz'],
            ['Foo Baz', $messages, 'foo.baz'],
        ];
    }

    public static function getInvalidLocalesTests()
    {
        return [
            ['fr FR'],
            ['français'],
            ['fr+en'],
            ['utf#8'],
            ['fr&en'],
            ['fr~FR'],
            [' fr'],
            ['fr '],
            ['fr*'],
            ['fr/FR'],
            ['fr\\FR'],
        ];
    }

    public static function getValidLocalesTests()
    {
        return [
            [''],
            ['fr'],
            ['francais'],
            ['FR'],
            ['frFR'],
            ['fr-FR'],
            ['fr_FR'],
            ['fr.FR'],
            ['fr-FR.UTF8'],
            ['sr@latin'],
        ];
    }

    /**
     * @requires extension intl
     */
    public function testIntlFormattedDomain()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());

        $translator->addResource('array', ['some_message' => 'Hello %name%'], 'en');
        $this->assertSame('Hello Bob', $translator->trans('some_message', ['%name%' => 'Bob']));

        $translator->addResource('array', ['some_message' => 'Hi {name}'], 'en', 'messages+intl-icu');
        $this->assertSame('Hi Bob', $translator->trans('some_message', ['%name%' => 'Bob']));
    }

    public function testIntlDomainOverlapseWithIntlResourceBefore()
    {
        $intlFormatterMock = $this->createMock(IntlFormatterInterface::class);
        $intlFormatterMock->expects($this->once())->method('formatIntl')->with('hello intl', 'en', [])->willReturn('hello intl');

        $messageFormatter = new MessageFormatter(null, $intlFormatterMock);

        $translator = new Translator('en', $messageFormatter);
        $translator->addLoader('array', new ArrayLoader());

        $translator->addResource('array', ['some_message' => 'hello intl'], 'en', 'messages+intl-icu');
        $translator->addResource('array', ['some_message' => 'hello'], 'en', 'messages');

        $this->assertSame('hello', $translator->trans('some_message', [], 'messages'));

        $translator->addResource('array', ['some_message' => 'hello intl'], 'en', 'messages+intl-icu');

        $this->assertSame('hello intl', $translator->trans('some_message', [], 'messages'));
    }

    public function testMissingLoaderForResourceError()
    {
        $translator = new Translator('en');
        $translator->addResource('twig', 'messages.en.twig', 'en');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No loader is registered for the "twig" format when loading the "messages.en.twig" resource.');

        $translator->getCatalogue('en');
    }
}

class StringClass
{
    public function __construct(
        protected string $str,
    ) {
    }

    public function __toString(): string
    {
        return $this->str;
    }
}
