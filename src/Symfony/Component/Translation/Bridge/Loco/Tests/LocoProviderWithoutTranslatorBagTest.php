<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Loco\Tests;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\Bridge\Loco\LocoProvider;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LocoProviderWithoutTranslatorBagTest extends LocoProviderTest
{
    public static function createProvider(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint, TranslatorBagInterface $translatorBag = null): ProviderInterface
    {
        return new LocoProvider($client, $loader, $logger, $defaultLocale, $endpoint, null);
    }

    /**
     * Ensure the Last-Modified is not sent when $translatorBag is null.
     *
     * @dataProvider getResponsesForReadWithLastModified
     */
    public function testReadWithLastModified(array $locales, array $domains, array $responseContents, array $lastModifieds, TranslatorBag $expectedTranslatorBag)
    {
        $responses = [];
        $consecutiveLoadArguments = [];
        $consecutiveLoadReturns = [];

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $responses[] = function (string $method, string $url, array $options = []) use ($responseContents, $lastModifieds, $locale, $domain): ResponseInterface {
                    $this->assertSame('GET', $method);
                    $this->assertSame('https://localise.biz/api/export/locale/'.$locale.'.xlf?filter='.rawurlencode($domain).'&status=translated%2Cblank-translation', $url);
                    $this->assertSame(['filter' => $domain, 'status' => 'translated,blank-translation'], $options['query']);
                    $this->assertSame(['Accept: */*'], $options['headers']);

                    return new MockResponse($responseContents[$locale][$domain], [
                        'response_headers' => [
                            'Last-Modified' => $lastModifieds[$locale],
                        ],
                    ]);
                };
                $consecutiveLoadArguments[] = [$responseContents[$locale][$domain], $locale, $domain];
                $consecutiveLoadReturns[] = (new XliffFileLoader())->load($responseContents[$locale][$domain], $locale, $domain);
            }
        }

        $loader = $this->getLoader();
        $consecutiveLoadArguments = array_merge($consecutiveLoadArguments, $consecutiveLoadArguments);
        $consecutiveLoadReturns = array_merge($consecutiveLoadReturns, $consecutiveLoadReturns);

        $loader->expects($this->exactly(\count($consecutiveLoadArguments)))
            ->method('load')
            ->willReturnCallback(function (...$args) use (&$consecutiveLoadArguments, &$consecutiveLoadReturns) {
                $this->assertSame(array_shift($consecutiveLoadArguments), $args);

                return array_shift($consecutiveLoadReturns);
            });

        $provider = $this->createProvider(
            new MockHttpClient($responses, 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/'
        );

        $translatorBag = $provider->read($domains, $locales);

        $this->assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());

        $responses = [];

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $responses[] = function (string $method, string $url, array $options = []) use ($responseContents, $lastModifieds, $locale, $domain): ResponseInterface {
                    $this->assertSame('GET', $method);
                    $this->assertSame('https://localise.biz/api/export/locale/'.$locale.'.xlf?filter='.rawurlencode($domain).'&status=translated%2Cblank-translation', $url);
                    $this->assertSame(['filter' => $domain, 'status' => 'translated,blank-translation'], $options['query']);
                    $this->assertNotContains('If-Modified-Since: '.$lastModifieds[$locale], $options['headers']);
                    $this->assertSame(['Accept: */*'], $options['headers']);

                    return new MockResponse($responseContents[$locale][$domain], [
                        'response_headers' => [
                            'Last-Modified' => $lastModifieds[$locale],
                        ],
                    ]);
                };
            }
        }

        $provider = $this->createProvider(
            new MockHttpClient($responses, 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/'
        );

        $translatorBag = $provider->read($domains, $locales);

        $this->assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
    }
}
