<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Test;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A test case to ease testing a translation provider.
 *
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @internal
 */
abstract class ProviderTestCase extends TestCase
{
    protected static HttpClientInterface $client;
    protected static LoggerInterface $logger;
    protected static string $defaultLocale;
    protected static LoaderInterface $loader;
    protected static XliffFileDumper $xliffFileDumper;
    protected static TranslatorBagInterface $translatorBag;

    abstract public static function createProvider(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint): ProviderInterface;

    /**
     * @return iterable<array{0: ProviderInterface, 1: string}>
     */
    abstract public static function toStringProvider(): iterable;

    /**
     * @dataProvider toStringProvider
     */
    public function testToString(ProviderInterface $provider, string $expected)
    {
        $this->assertSame($expected, (string) $provider);
    }

    protected static function getClient(): MockHttpClient
    {
        return static::$client ??= new MockHttpClient();
    }

    protected static function getLoader(): LoaderInterface
    {
        return static::$loader ??= new class() implements LoaderInterface {
            public function load($resource, string $locale, string $domain = 'messages'): MessageCatalogue
            {
                return new MessageCatalogue($locale);
            }
        };
    }

    protected static function getLogger(): LoggerInterface
    {
        return static::$logger ??= new NullLogger();
    }

    protected static function getDefaultLocale(): string
    {
        return static::$defaultLocale ??= 'en';
    }

    protected static function getXliffFileDumper(): XliffFileDumper
    {
        return static::$xliffFileDumper ??= new XliffFileDumper();
    }

    protected static function getTranslatorBag(): TranslatorBagInterface
    {
        return self::$translatorBag ??= new TranslatorBag();
    }
}
