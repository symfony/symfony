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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Translation\Bridge\Phrase\PhraseProviderFactory;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Component\Translation\Exception\MissingRequiredOptionException;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\Dsn;

/**
 * @author wicliff <wicliff.wolda@gmail.com>
 */
class PhraseProviderFactoryTest extends TestCase
{
    private MockObject&MockHttpClient $httpClient;
    private MockObject&LoggerInterface $logger;
    private MockObject&LoaderInterface $loader;
    private MockObject&XliffFileDumper $xliffFileDumper;
    private MockObject&CacheItemPoolInterface $cache;
    private string $defaultLocale;

    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(bool $expected, string $dsn)
    {
        $factory = $this->createFactory();

        $this->assertSame($expected, $factory->supports(new Dsn($dsn)));
    }

    /**
     * @dataProvider createProvider
     */
    public function testCreate(string $expected, string $dsn)
    {
        $factory = $this->createFactory();
        $provider = $factory->create(new Dsn($dsn));

        $this->assertSame($expected, (string) $provider);
    }

    /**
     * @dataProvider unsupportedSchemeProvider
     */
    public function testUnsupportedSchemeException(string $dsn, string $message)
    {
        $factory = $this->createFactory();
        $dsn = new Dsn($dsn);

        $this->expectException(UnsupportedSchemeException::class);
        $this->expectExceptionMessage($message);

        $factory->create($dsn);
    }

    /**
     * @dataProvider incompleteDsnProvider
     */
    public function testIncompleteDsnException(string $dsn, string $message)
    {
        $factory = $this->createFactory();
        $dsn = new Dsn($dsn);

        $this->expectException(IncompleteDsnException::class);
        $this->expectExceptionMessage($message);

        $factory->create($dsn);
    }

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
        $this->getHttpClient()
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

        $this->createFactory()
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

    private function createFactory(): PhraseProviderFactory
    {
        return new PhraseProviderFactory(
            $this->getHttpClient(),
            $this->getLogger(),
            $this->getLoader(),
            $this->getXliffFileDumper(),
            $this->getCache(),
            $this->getDefaultLocale()
        );
    }

    private function getHttpClient(): MockObject&MockHttpClient
    {
        return $this->httpClient ??= $this->createMock(MockHttpClient::class);
    }

    private function getLogger(): MockObject&LoggerInterface
    {
        return $this->logger ??= $this->createMock(LoggerInterface::class);
    }

    private function getLoader(): MockObject&LoaderInterface
    {
        return $this->loader ??= $this->createMock(LoaderInterface::class);
    }

    private function getXliffFileDumper(): XliffFileDumper&MockObject
    {
        return $this->xliffFileDumper ??= $this->createMock(XliffFileDumper::class);
    }

    private function getCache(): MockObject&CacheItemPoolInterface
    {
        return $this->cache ??= $this->createMock(CacheItemPoolInterface::class);
    }

    private function getDefaultLocale(): string
    {
        return $this->defaultLocale ??= 'en_GB';
    }
}
