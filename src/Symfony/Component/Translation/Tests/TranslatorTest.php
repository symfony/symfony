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
use Symfony\Component\Translation\Formatter\IntlFormatter;
use Symfony\Component\Translation\Formatter\IntlFormatterInterface;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator;

class TranslatorTest extends TestCase
{
    private $defaultLocale;

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
        self::expectException(InvalidArgumentException::class);
        new Translator($locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testConstructorValidLocale($locale)
    {
        $translator = new Translator($locale);

        self::assertSame($locale ?: (class_exists(\Locale::class) ? \Locale::getDefault() : 'en'), $translator->getLocale());
    }

    public function testSetGetLocale()
    {
        $translator = new Translator('en');

        self::assertEquals('en', $translator->getLocale());

        $translator->setLocale('fr');
        self::assertEquals('fr', $translator->getLocale());
    }

    /**
     * @dataProvider getInvalidLocalesTests
     */
    public function testSetInvalidLocale($locale)
    {
        self::expectException(InvalidArgumentException::class);
        $translator = new Translator('fr');
        $translator->setLocale($locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testSetValidLocale($locale)
    {
        $translator = new Translator($locale);
        $translator->setLocale($locale);

        self::assertEquals($locale ?: (class_exists(\Locale::class) ? \Locale::getDefault() : 'en'), $translator->getLocale());
    }

    public function testGetCatalogue()
    {
        $translator = new Translator('en');

        self::assertEquals(new MessageCatalogue('en'), $translator->getCatalogue());

        $translator->setLocale('fr');
        self::assertEquals(new MessageCatalogue('fr'), $translator->getCatalogue('fr'));
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
        self::assertTrue($catalogue->defines('foo', 'domain-a'));
        self::assertTrue($catalogue->defines('bar', 'domain-b'));
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
        self::assertEquals('foobar', $translator->trans('bar'));
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
        self::assertEquals('bar (fr)', $translator->trans('bar'));
    }

    /**
     * @dataProvider getInvalidLocalesTests
     */
    public function testSetFallbackInvalidLocales($locale)
    {
        self::expectException(InvalidArgumentException::class);
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
        self::addToAssertionCount(1);
    }

    public function testTransWithFallbackLocale()
    {
        $translator = new Translator('fr_FR');
        $translator->setFallbackLocales(['en']);

        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['bar' => 'foobar'], 'en');

        self::assertEquals('foobar', $translator->trans('bar'));
    }

    /**
     * @dataProvider getInvalidLocalesTests
     */
    public function testAddResourceInvalidLocales($locale)
    {
        self::expectException(InvalidArgumentException::class);
        $translator = new Translator('fr');
        $translator->addResource('array', ['foo' => 'foofoo'], $locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testAddResourceValidLocales($locale)
    {
        $translator = new Translator('fr');
        $translator->addResource('array', ['foo' => 'foofoo'], $locale);
        // no assertion. this method just asserts that no exception is thrown
        self::addToAssertionCount(1);
    }

    public function testAddResourceAfterTrans()
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());

        $translator->setFallbackLocales(['en']);

        $translator->addResource('array', ['foo' => 'foofoo'], 'en');
        self::assertEquals('foofoo', $translator->trans('foo'));

        $translator->addResource('array', ['bar' => 'foobar'], 'en');
        self::assertEquals('foobar', $translator->trans('bar'));
    }

    /**
     * @dataProvider getTransFileTests
     */
    public function testTransWithoutFallbackLocaleFile($format, $loader)
    {
        self::expectException(NotFoundResourceException::class);
        $loaderClass = 'Symfony\\Component\\Translation\\Loader\\'.$loader;
        $translator = new Translator('en');
        $translator->addLoader($format, new $loaderClass());
        $translator->addResource($format, __DIR__.'/fixtures/non-existing', 'en');
        $translator->addResource($format, __DIR__.'/fixtures/resources.'.$format, 'en');

        // force catalogue loading
        $translator->trans('foo');
    }

    /**
     * @dataProvider getTransFileTests
     */
    public function testTransWithFallbackLocaleFile($format, $loader)
    {
        $loaderClass = 'Symfony\\Component\\Translation\\Loader\\'.$loader;
        $translator = new Translator('en_GB');
        $translator->addLoader($format, new $loaderClass());
        $translator->addResource($format, __DIR__.'/fixtures/non-existing', 'en_GB');
        $translator->addResource($format, __DIR__.'/fixtures/resources.'.$format, 'en', 'resources');

        self::assertEquals('bar', $translator->trans('foo', [], 'resources'));
    }

    public function testTransWithIcuFallbackLocale()
    {
        $translator = new Translator('en_GB');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en_GB');
        $translator->addResource('array', ['bar' => 'foobar'], 'en_001');
        $translator->addResource('array', ['baz' => 'foobaz'], 'en');
        self::assertSame('foofoo', $translator->trans('foo'));
        self::assertSame('foobar', $translator->trans('bar'));
        self::assertSame('foobaz', $translator->trans('baz'));
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

        self::assertSame('foofoo', $translator->trans('foo'));
        self::assertSame('foobar', $translator->trans('bar'));
        self::assertSame('foobaz', $translator->trans('baz'));
        self::assertSame('fooqux', $translator->trans('qux'));
        self::assertSame('nl_NL', $translator->trans('fallback'));
    }

    public function testTransWithIcuRootFallbackLocale()
    {
        $translator = new Translator('az_Cyrl');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'az_Cyrl');
        $translator->addResource('array', ['bar' => 'foobar'], 'az');
        self::assertSame('foofoo', $translator->trans('foo'));
        self::assertSame('bar', $translator->trans('bar'));
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
        self::assertEquals('foobar', $translator->trans('bar'));
    }

    public function getFallbackLocales()
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

        self::assertEquals('foo (en_US)', $translator->trans('foo'));
        self::assertEquals('bar (en)', $translator->trans('bar'));
    }

    public function testTransNonExistentWithFallback()
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('array', new ArrayLoader());
        self::assertEquals('non-existent', $translator->trans('non-existent'));
    }

    public function testWhenAResourceHasNoRegisteredLoader()
    {
        self::expectException(RuntimeException::class);
        $translator = new Translator('en');
        $translator->addResource('array', ['foo' => 'foofoo'], 'en');

        $translator->trans('foo');
    }

    public function testNestedFallbackCatalogueWhenUsingMultipleLocales()
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['ru', 'en']);

        $translator->getCatalogue('fr');

        self::assertNotNull($translator->getCatalogue('ru')->getFallbackCatalogue());
    }

    public function testFallbackCatalogueResources()
    {
        $translator = new Translator('en_GB');
        $translator->addLoader('yml', new \Symfony\Component\Translation\Loader\YamlFileLoader());
        $translator->addResource('yml', __DIR__.'/fixtures/empty.yml', 'en_GB');
        $translator->addResource('yml', __DIR__.'/fixtures/resources.yml', 'en');

        // force catalogue loading
        self::assertEquals('bar', $translator->trans('foo', []));

        $resources = $translator->getCatalogue('en')->getResources();
        self::assertCount(1, $resources);
        self::assertContainsEquals(__DIR__.\DIRECTORY_SEPARATOR.'fixtures'.\DIRECTORY_SEPARATOR.'resources.yml', $resources);

        $resources = $translator->getCatalogue('en_GB')->getResources();
        self::assertCount(2, $resources);
        self::assertContainsEquals(__DIR__.\DIRECTORY_SEPARATOR.'fixtures'.\DIRECTORY_SEPARATOR.'empty.yml', $resources);
        self::assertContainsEquals(__DIR__.\DIRECTORY_SEPARATOR.'fixtures'.\DIRECTORY_SEPARATOR.'resources.yml', $resources);
    }

    /**
     * @dataProvider getTransTests
     */
    public function testTrans($expected, $id, $translation, $parameters, $locale, $domain)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [(string) $id => $translation], $locale, $domain);

        self::assertEquals($expected, $translator->trans($id, $parameters, $domain, $locale));
    }

    /**
     * @dataProvider getTransICUTests
     */
    public function testTransICU(...$args)
    {
        if (!class_exists(\MessageFormatter::class)) {
            self::markTestSkipped(sprintf('Skipping test as the required "%s" class does not exist. Consider installing the "intl" PHP extension or the "symfony/polyfill-intl-messageformatter" package.', \MessageFormatter::class));
        }

        $this->testTrans(...$args);
    }

    /**
     * @dataProvider getInvalidLocalesTests
     */
    public function testTransInvalidLocale($locale)
    {
        self::expectException(InvalidArgumentException::class);
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en');

        $translator->trans('foo', [], '', $locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testTransValidLocale($locale)
    {
        $translator = new Translator($locale);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['test' => 'OK'], $locale);

        self::assertEquals('OK', $translator->trans('test'));
        self::assertEquals('OK', $translator->trans('test', [], null, $locale));
    }

    /**
     * @dataProvider getFlattenedTransTests
     */
    public function testFlattenedTrans($expected, $messages, $id)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $messages, 'fr', '');

        self::assertEquals($expected, $translator->trans($id, [], '', 'fr'));
    }

    public function testTransNullId()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en');

        self::assertSame('', $translator->trans(null));

        (\Closure::bind(function () use ($translator) {
            $this->assertSame([], $translator->catalogues);
        }, $this, Translator::class))();
    }

    public function getTransFileTests()
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

    public function getTransTests()
    {
        return [
            ['Symfony est super !', 'Symfony is great!', 'Symfony est super !', [], 'fr', ''],
            ['Symfony est awesome !', 'Symfony is %what%!', 'Symfony est %what% !', ['%what%' => 'awesome'], 'fr', ''],
            ['Symfony est super !', new StringClass('Symfony is great!'), 'Symfony est super !', [], 'fr', ''],
            ['', null, '', [], 'fr', ''],
        ];
    }

    public function getTransICUTests()
    {
        $id = '{apples, plural, =0 {There are no apples} one {There is one apple} other {There are # apples}}';

        return [
            ['There are no apples', $id, $id, ['{apples}' => 0], 'en', 'test'.MessageCatalogue::INTL_DOMAIN_SUFFIX],
            ['There is one apple',  $id, $id, ['{apples}' => 1], 'en', 'test'.MessageCatalogue::INTL_DOMAIN_SUFFIX],
            ['There are 3 apples',  $id, $id, ['{apples}' => 3], 'en', 'test'.MessageCatalogue::INTL_DOMAIN_SUFFIX],
        ];
    }

    public function getFlattenedTransTests()
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

    public function getInvalidLocalesTests()
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

    public function getValidLocalesTests()
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
        self::assertSame('Hello Bob', $translator->trans('some_message', ['%name%' => 'Bob']));

        $translator->addResource('array', ['some_message' => 'Hi {name}'], 'en', 'messages+intl-icu');
        self::assertSame('Hi Bob', $translator->trans('some_message', ['%name%' => 'Bob']));
    }

    public function testIntlDomainOverlapseWithIntlResourceBefore()
    {
        $intlFormatterMock = self::createMock(IntlFormatterInterface::class);
        $intlFormatterMock->expects(self::once())->method('formatIntl')->with('hello intl', 'en', [])->willReturn('hello intl');

        $messageFormatter = new MessageFormatter(null, $intlFormatterMock);

        $translator = new Translator('en', $messageFormatter);
        $translator->addLoader('array', new ArrayLoader());

        $translator->addResource('array', ['some_message' => 'hello intl'], 'en', 'messages+intl-icu');
        $translator->addResource('array', ['some_message' => 'hello'], 'en', 'messages');

        self::assertSame('hello', $translator->trans('some_message', [], 'messages'));

        $translator->addResource('array', ['some_message' => 'hello intl'], 'en', 'messages+intl-icu');

        self::assertSame('hello intl', $translator->trans('some_message', [], 'messages'));
    }

    public function testMissingLoaderForResourceError()
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('No loader is registered for the "twig" format when loading the "messages.en.twig" resource.');

        $translator = new Translator('en');
        $translator->addResource('twig', 'messages.en.twig', 'en');
        $translator->getCatalogue('en');
    }
}

class StringClass
{
    protected $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public function __toString(): string
    {
        return $this->str;
    }
}
