<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Phrase\Tests;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Translation\Bridge\Phrase\PhraseProviderFactory;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\MissingRequiredOptionException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Test\AbstractProviderFactoryTestCase;
use Symfony\Component\Translation\Test\IncompleteDsnTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author wicliff <wicliff.wolda@gmail.com>
 */
class PhraseProviderFactoryTest extends AbstractProviderFactoryTestCase
{
    use IncompleteDsnTestTrait;

    private MockHttpClient $httpClient;
    private LoggerInterface $logger;
    private LoaderInterface $loader;
    private XliffFileDumper $xliffFileDumper;
    private CacheItemPoolInterface $cache;
    private string $defaultLocale;

    public function testRequiredUserAgentOption()
    {
        $factory = $this->createFactory();
        $dsn = new Dsn('phrase://PROJECT_ID:API_TOKEN@default');

        $this->expectException(MissingRequiredOptionException::class);
        $this->expectExceptionMessage('The option "userAgent" is required but missing.');

        $factory->create($dsn);
    }

    public function testHttpClientConfig()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('withOptions')
            ->with([
                'base_uri' => 'https://api.us.app.phrase.com:8080/v2/projects/PROJECT_ID/',
                'headers' => [
                    'Authorization' => 'token API_TOKEN',
                    'User-Agent' => 'myProject',
                ],
            ]);

        $dsn = new Dsn('phrase://PROJECT_ID:API_TOKEN@api.us.app.phrase.com:8080?userAgent=myProject');

        $this->createFactory($httpClient)
            ->create($dsn);
    }

    public static function createProvider(): \Generator
    {
        yield 'default datacenter' => [
            'phrase://api.phrase.com',
            'phrase://PROJECT_ID:API_TOKEN@default?userAgent=myProject',
        ];

        yield 'us datacenter' => [
            'phrase://api.us.app.phrase.com:8080',
            'phrase://PROJECT_ID:API_TOKEN@api.us.app.phrase.com:8080?userAgent=myProject',
        ];
    }

    public static function incompleteDsnProvider(): \Generator
    {
        yield ['phrase://default', 'Invalid "phrase://default" provider DSN: User is not set.'];
    }

    public static function unsupportedSchemeProvider(): \Generator
    {
        yield ['unsupported://API_TOKEN@default', 'The "unsupported" scheme is not supported; supported schemes for translation provider "phrase" are: "phrase".'];
    }

    public static function supportsProvider(): \Generator
    {
        yield 'supported' => [true, 'phrase://PROJECT_ID:API_TOKEN@default?userAgent=myProject'];
        yield 'not supported' => [false, 'unsupported://PROJECT_ID:API_TOKEN@default?userAgent=myProject'];
    }

    public function createFactory(?HttpClientInterface $httpClient = null): PhraseProviderFactory
    {
        return new PhraseProviderFactory(
            $httpClient ?? $this->getHttpClient(),
            $this->getLogger(),
            $this->getLoader(),
            $this->getXliffFileDumper(),
            $this->getCache(),
            $this->getDefaultLocale()
        );
    }

    private function getHttpClient(): MockHttpClient
    {
        return $this->httpClient ??= new MockHttpClient();
    }

    private function getLogger(): LoggerInterface
    {
        return $this->logger ??= new NullLogger();
    }

    private function getLoader(): LoaderInterface
    {
        return $this->loader ??= new ArrayLoader();
    }

    private function getXliffFileDumper(): XliffFileDumper
    {
        return $this->xliffFileDumper ??= new XliffFileDumper();
    }

    private function getCache(): CacheItemPoolInterface
    {
        return $this->cache ??= new NullAdapter();
    }

    private function getDefaultLocale(): string
    {
        return $this->defaultLocale ??= 'en_GB';
    }
}
