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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\Bridge\Loco\LocoProvider;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Test\ProviderTestCase;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LocoProviderTest extends ProviderTestCase
{
    use ExpectUserDeprecationMessageTrait;

    public static function createProvider(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint, ?TranslatorBagInterface $translatorBag = null, ?string $restrictToStatus = null, XliffFileDumper $dumper = new XliffFileDumper()): ProviderInterface
    {
        return new LocoProvider($client, $loader, $logger, $endpoint, $translatorBag ?? new TranslatorBag(), $restrictToStatus, $dumper);
    }

    public static function toStringProvider(): iterable
    {
        yield [
            static::createProvider((new MockHttpClient())->withOptions([
                'base_uri' => 'https://localise.biz/api/',
                'headers' => [
                    'Authorization' => 'Loco API_KEY',
                ],
            ]), new ArrayLoader(), new NullLogger(), 'en', 'localise.biz/api/'),
            'loco://localise.biz/api/',
        ];

        yield [
            static::createProvider((new MockHttpClient())->withOptions([
                'base_uri' => 'https://example.com',
                'headers' => [
                    'Authorization' => 'Loco API_KEY',
                ],
            ]), new ArrayLoader(), new NullLogger(), 'en', 'example.com'),
            'loco://example.com',
        ];

        yield [
            static::createProvider((new MockHttpClient())->withOptions([
                'base_uri' => 'https://example.com:99',
                'headers' => [
                    'Authorization' => 'Loco API_KEY',
                ],
            ]), new ArrayLoader(), new NullLogger(), 'en', 'example.com:99'),
            'loco://example.com:99',
        ];
    }

    public function testCompleteWriteProcess()
    {
        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'getLocales' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://localise.biz/api/locales', $url);
                $this->assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"code":"en"}]');
            },
            'createMissingFrLocale' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://localise.biz/api/locales', $url);
                $this->assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertSame(http_build_query(['code' => 'fr']), $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
            'importMessagesEn' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertSame(['locale' => 'en', 'tag-new' => 'messages'], $options['query']);
                $this->assertStringContainsString('source-language="en"', $options['body']);
                $this->assertStringContainsString('target-language="en"', $options['body']);
                $this->assertStringContainsString('resname="messages__a"', $options['body']);

                return new MockResponse('', ['http_code' => 200]);
            },
            'importValidatorsEn' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertSame(['locale' => 'en', 'tag-new' => 'validators'], $options['query']);
                $this->assertStringContainsString('source-language="en"', $options['body']);
                $this->assertStringContainsString('target-language="en"', $options['body']);
                $this->assertStringContainsString('resname="validators__post.num_comments"', $options['body']);

                return new MockResponse('', ['http_code' => 200]);
            },
            'importMessagesFr' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertSame(['locale' => 'fr', 'tag-new' => 'messages'], $options['query']);
                $this->assertStringContainsString('source-language="fr"', $options['body']);
                $this->assertStringContainsString('target-language="fr"', $options['body']);
                $this->assertStringContainsString('resname="messages__a"', $options['body']);

                return new MockResponse('', ['http_code' => 200]);
            },
            'importValidatorsFr' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertSame(['locale' => 'fr', 'tag-new' => 'validators'], $options['query']);
                $this->assertStringContainsString('source-language="fr"', $options['body']);
                $this->assertStringContainsString('target-language="fr"', $options['body']);
                $this->assertStringContainsString('resname="validators__post.num_comments"', $options['body']);

                return new MockResponse('', ['http_code' => 200]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# comment} other {# comments}}'],
        ]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', [
            'messages' => ['a' => 'trans_fr_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# commentaire} other {# commentaires}}'],
        ]));

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        $provider->write($translatorBag);
    }

    public function testWriteDoesNotCreateLocalesLocoAlreadyHas()
    {
        $responses = [
            'getLocales' => static fn () => new JsonMockResponse([['code' => 'fr-FR']]),
            'importMessagesFrFr' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame(['locale' => 'fr_FR', 'tag-new' => 'messages'], $options['query']);

                return new MockResponse('', ['http_code' => 200]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('fr_FR', [
            'messages' => ['a' => 'trans_fr_a'],
        ]));

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        $provider->write($translatorBag);
    }

    public function testWriteCreateLocaleServerError()
    {
        $responses = [
            'getLocales' => static fn () => new MockResponse('[]'),
            'createMissingEnLocale' => static fn () => new MockResponse('', ['http_code' => 500]),
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to create locale "en" on Loco.');

        $provider->write($translatorBag);
    }

    public function testWriteImportServerError()
    {
        $responses = [
            'getLocales' => static fn () => new MockResponse('[{"code": "en"}]'),
            'importMessagesEn' => static fn () => new MockResponse('', ['http_code' => 500]),
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to import domain "messages" for locale "en" to Loco.');

        $provider->write($translatorBag);
    }

    #[DataProvider('getResponsesForOneLocaleAndOneDomain')]
    public function testReadForOneLocaleAndOneDomain(string $locale, string $domain, string $responseContent, TranslatorBag $expectedTranslatorBag)
    {
        $this->loader = $this->createMock(LoaderInterface::class);
        $this->loader->expects($this->once())
            ->method('load')
            ->willReturn((new XliffFileLoader())->load($responseContent, $locale, $domain));

        $provider = self::createProvider((new MockHttpClient(new MockResponse($responseContent)))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => [
                'Authorization' => 'Loco API_KEY',
            ],
        ]), $this->loader, new NullLogger(), 'en', 'localise.biz/api/');
        $translatorBag = $provider->read([$domain], [$locale]);
        // We don't want to assert equality of metadata here, due to the ArrayLoader usage.
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $catalogue->deleteMetadata('', '');
        }

        $this->assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
    }

    #[DataProvider('getResponsesForManyLocalesAndManyDomains')]
    public function testReadForManyLocalesAndManyDomains(array $locales, array $domains, array $responseContents, TranslatorBag $expectedTranslatorBag)
    {
        $responses = [];

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $responses[] = new MockResponse($responseContents[$locale][$domain]);
            }
        }

        $this->loader = $this->createMock(LoaderInterface::class);
        $this->loader->expects($this->exactly(\count($responses)))
            ->method('load')
            ->willReturnCallback(function (string $resource, string $locale, string $domain) use ($responseContents) {
                $this->assertSame($responseContents[$locale][$domain], $resource);

                return (new XliffFileLoader())->load($resource, $locale, $domain);
            });

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => [
                'Authorization' => 'Loco API_KEY',
            ],
        ]), $this->loader, $this->getLogger(), 'en', 'localise.biz/api/');
        $translatorBag = $provider->read($domains, $locales);
        // We don't want to assert equality of metadata here, due to the ArrayLoader usage.
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $catalogue->deleteMetadata('', '');
        }

        $this->assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
    }

    public function testReadForNoLocales()
    {
        $responses = [
            'getLocales' => function (string $method, string $url): MockResponse {
                $this->assertSame('GET', $method);
                $this->assertSame('https://localise.biz/api/locales', $url);

                return new JsonMockResponse([['code' => 'en'], ['code' => 'fr']]);
            },
            'getEnMessages' => function (string $method, string $url): MockResponse {
                $this->assertSame('GET', $method);
                $this->assertSame('https://localise.biz/api/export/locale/en.xlf?filter=messages&status=translated%2Cblank-translation', $url);

                return new MockResponse(<<<'XLIFF'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
                      <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                        <header>
                          <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                        </header>
                        <body>
                          <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
                            <source>index.hello</source>
                            <target state="translated">Hello</target>
                          </trans-unit>
                        </body>
                      </file>
                    </xliff>
                    XLIFF);
            },
            'getFrMessages' => function (string $method, string $url): MockResponse {
                $this->assertSame('GET', $method);
                $this->assertSame('https://localise.biz/api/export/locale/fr.xlf?filter=messages&status=translated%2Cblank-translation', $url);

                return new MockResponse(<<<'XLIFF'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
                      <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                        <header>
                          <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                        </header>
                        <body>
                          <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
                            <source>index.hello</source>
                            <target state="translated">Bonjour</target>
                          </trans-unit>
                        </body>
                      </file>
                    </xliff>
                    XLIFF);
            },
        ];

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => [
                'Authorization' => 'Loco API_KEY',
            ],
        ]), new XliffFileLoader(), $this->getLogger(), 'en', 'localise.biz/api/');

        $this->assertEquals(
            ['en', 'fr'],
            array_map(
                static fn (MessageCatalogue $catalogue) => $catalogue->getLocale(),
                $provider->read(['messages'], [])->getCatalogues()
            ),
        );
    }

    #[DataProvider('getResponsesForReadWithLastModified')]
    public function testReadWithLastModified(array $locales, array $domains, array $responseContents, array $lastModifieds, TranslatorBag $expectedTranslatorBag)
    {
        $responses = [];

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
            }
        }

        $this->loader = $this->createMock(LoaderInterface::class);
        $this->loader->expects($this->exactly(\count($responses)))
            ->method('load')
            ->willReturnCallback(function (string $resource, string $locale, string $domain) use ($responseContents) {
                $this->assertSame($responseContents[$locale][$domain], $resource);

                return (new XliffFileLoader())->load($resource, $locale, $domain);
            });

        $provider = self::createProvider(
            new MockHttpClient($responses, 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/'
        );

        $this->translatorBag = $provider->read($domains, $locales);

        $responses = [];

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $responses[] = function (string $method, string $url, array $options = []) use ($responseContents, $lastModifieds, $locale, $domain): ResponseInterface {
                    $this->assertSame('GET', $method);
                    $this->assertSame('https://localise.biz/api/export/locale/'.$locale.'.xlf?filter='.rawurlencode($domain).'&status=translated%2Cblank-translation', $url);
                    $this->assertSame(['filter' => $domain, 'status' => 'translated,blank-translation'], $options['query']);
                    $this->assertSame(['If-Modified-Since: '.$lastModifieds[$locale], 'Accept: */*'], $options['headers']);

                    return new MockResponse($responseContents[$locale][$domain], [
                        'http_code' => 304,
                        'response_headers' => [
                            'Last-Modified' => $lastModifieds[$locale],
                        ],
                    ]);
                };
            }
        }

        $provider = self::createProvider(
            new MockHttpClient($responses, 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/',
            $this->getTranslatorBag()
        );

        $translatorBag = $provider->read($domains, $locales);

        $this->assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
    }

    public function testDeleteProcess()
    {
        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# comment} other {# comments}}'],
        ]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', [
            'messages' => ['a' => 'trans_fr_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# commentaire} other {# commentaires}}'],
        ]));

        $provider = self::createProvider(
            new MockHttpClient([
                function (string $method, string $url): MockResponse {
                    $this->assertSame('DELETE', $method);
                    $this->assertSame('https://localise.biz/api/assets/messages__a.json', $url);

                    return new MockResponse();
                },
                function (string $method, string $url): MockResponse {
                    $this->assertSame('DELETE', $method);
                    $this->assertSame('https://localise.biz/api/assets/validators__post.num_comments.json', $url);

                    return new MockResponse();
                },
            ], 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/'
        );

        $provider->delete($translatorBag);
    }

    public function testDeleteServerError()
    {
        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));

        $provider = self::createProvider(
            new MockHttpClient([
                function (string $method, string $url): MockResponse {
                    $this->assertSame('DELETE', $method);
                    $this->assertSame('https://localise.biz/api/assets/messages__a.json', $url);

                    return new MockResponse('', ['http_code' => 500]);
                },
            ], 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/'
        );

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to delete translation key "messages__a" to Loco.');

        $provider->delete($translatorBag);
    }

    public static function getResponsesForOneLocaleAndOneDomain(): \Generator
    {
        $arrayLoader = new ArrayLoader();

        $expectedTranslatorBagEn = new TranslatorBag();
        $expectedTranslatorBagEn->addCatalogue($arrayLoader->load([
            'index.hello' => 'Hello',
            'index.greetings' => 'Welcome, {firstname}!',
        ], 'en', 'messages+intl-icu'));

        yield ['en', 'messages+intl-icu', <<<'XLIFF'
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
              <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                <header>
                  <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                </header>
                <body>
                  <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext" extradata="loco:format=icu">
                    <source>index.hello</source>
                    <target state="translated">Hello</target>
                  </trans-unit>
                  <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
                    <source>index.greetings</source>
                    <target state="translated">Welcome, {firstname}!</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>
            XLIFF,
            $expectedTranslatorBagEn,
        ];

        $expectedTranslatorBagFr = new TranslatorBag();
        $expectedTranslatorBagFr->addCatalogue($arrayLoader->load([
            'index.hello' => 'Bonjour',
            'index.greetings' => 'Bienvenue, {firstname} !',
        ], 'fr', 'messages+intl-icu'));

        yield ['fr', 'messages+intl-icu', <<<'XLIFF'
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
              <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                <header>
                  <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                </header>
                <body>
                  <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext" extradata="loco:format=icu">
                    <source>index.hello</source>
                    <target state="translated">Bonjour</target>
                  </trans-unit>
                  <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
                    <source>index.greetings</source>
                    <target state="translated">Bienvenue, {firstname} !</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>
            XLIFF,
            $expectedTranslatorBagFr,
        ];
    }

    public static function getResponsesForManyLocalesAndManyDomains(): \Generator
    {
        $arrayLoader = new ArrayLoader();

        $expectedTranslatorBag = new TranslatorBag();
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'index.hello' => 'Hello',
        ], 'en'));
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'index.greetings' => 'Welcome, {firstname}!',
        ], 'en', 'messages+intl-icu'));
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'index.hello' => 'Bonjour',
        ], 'fr'));
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'index.greetings' => 'Bienvenue, {firstname} !',
        ], 'fr', 'messages+intl-icu'));
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'firstname.error' => 'Firstname must contains only letters.',
            'lastname.error' => 'Lastname must contains only letters.',
        ], 'en', 'validators'));
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'firstname.error' => 'Le prénom ne peut contenir que des lettres.',
            'lastname.error' => 'Le nom de famille ne peut contenir que des lettres.',
        ], 'fr', 'validators'));

        yield [
            ['en', 'fr'],
            ['messages', 'messages+intl-icu', 'validators'],
            [
                'en' => [
                    'messages' => <<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
                          <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                            <header>
                              <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                            </header>
                            <body>
                              <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
                                <source>index.hello</source>
                                <target state="translated">Hello</target>
                              </trans-unit>
                            </body>
                          </file>
                        </xliff>
                        XLIFF,
                    'messages+intl-icu' => <<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
                          <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                            <header>
                              <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                            </header>
                            <body>
                              <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
                                <source>index.greetings</source>
                                <target state="translated">Welcome, {firstname}!</target>
                              </trans-unit>
                            </body>
                          </file>
                        </xliff>
                        XLIFF,
                    'validators' => <<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
                          <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                            <header>
                              <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                            </header>
                            <body>
                              <trans-unit id="loco:5fd89b853ee27904dd6c5f68" resname="firstname.error" datatype="plaintext">
                                <source>firstname.error</source>
                                <target state="translated">Firstname must contains only letters.</target>
                              </trans-unit>
                              <trans-unit id="loco:5fd89b8542e5aa5cc27457e3" resname="lastname.error" datatype="plaintext" extradata="loco:format=icu">
                                <source>lastname.error</source>
                                <target state="translated">Lastname must contains only letters.</target>
                              </trans-unit>
                            </body>
                          </file>
                        </xliff>
                        XLIFF,
                ],
                'fr' => [
                    'messages' => <<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
                          <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                            <header>
                              <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                            </header>
                            <body>
                              <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
                                <source>index.hello</source>
                                <target state="translated">Bonjour</target>
                              </trans-unit>
                            </body>
                          </file>
                        </xliff>
                        XLIFF,
                    'messages+intl-icu' => <<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
                          <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                            <header>
                              <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                            </header>
                            <body>
                              <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
                                <source>index.greetings</source>
                                <target state="translated">Bienvenue, {firstname} !</target>
                              </trans-unit>
                            </body>
                          </file>
                        </xliff>
                        XLIFF,
                    'validators' => <<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
                          <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                            <header>
                              <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                            </header>
                            <body>
                              <trans-unit id="loco:5fd89b853ee27904dd6c5f68" resname="firstname.error" datatype="plaintext">
                                <source>firstname.error</source>
                                <target state="translated">Le prénom ne peut contenir que des lettres.</target>
                              </trans-unit>
                              <trans-unit id="loco:5fd89b8542e5aa5cc27457e3" resname="lastname.error" datatype="plaintext" extradata="loco:format=icu">
                                <source>lastname.error</source>
                                <target state="translated">Le nom de famille ne peut contenir que des lettres.</target>
                              </trans-unit>
                            </body>
                          </file>
                        </xliff>
                        XLIFF,
                ],
            ],
            $expectedTranslatorBag,
        ];
    }

    public static function getResponsesForReadWithLastModified(): \Generator
    {
        $lastModifieds = [
            'en' => 'Tue, 16 Nov 2021 11:35:24 GMT',
            'fr' => 'Wed, 17 Nov 2021 11:22:33 GMT',
        ];

        foreach (self::getResponsesForManyLocalesAndManyDomains() as [$locales, $domains, $responseContents, $expectedTranslatorBag]) {
            foreach ($locales as $locale) {
                foreach ($domains as $domain) {
                    $catalogue = $expectedTranslatorBag->getCatalogue($locale);
                    $catalogue->setCatalogueMetadata('last-modified', $lastModifieds[$locale], $domain);
                }
            }

            yield [$locales, $domains, $responseContents, $lastModifieds, $expectedTranslatorBag];
        }
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[TestWith([[]])]
    #[TestWith([['*']])]
    public function testReadForAllDomains(array $domains)
    {
        $this->loader = $this->createMock(LoaderInterface::class);
        $this->loader->expects($this->once())
            ->method('load')
            ->willReturn(new MessageCatalogue('fr'));

        $provider = self::createProvider(
            new MockHttpClient([
                function (string $method, string $url, array $options = []): ResponseInterface {
                    $this->assertSame('GET', $method);
                    $this->assertSame('https://localise.biz/api/export/locale/fr.xlf?filter=&status=translated%2Cblank-translation', $url);
                    $this->assertSame(['filter' => '', 'status' => 'translated,blank-translation'], $options['query']);

                    return new MockResponse();
                },
            ], 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/',
        );

        $this->expectUserDeprecationMessage('Since symfony/loco-translation-provider 8.2: Passing no domains or "*" to "Symfony\Component\Translation\Bridge\Loco\LocoProvider::read" is deprecated, configure your loco provider domains as an associative array with an empty string key and "*" as value.');

        $this->translatorBag = $provider->read($domains, ['fr']);
    }

    public function testReadWithRestrictToStatus()
    {
        $this->loader = $this->createMock(LoaderInterface::class);
        $this->loader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new MessageCatalogue('fr'));

        $provider = self::createProvider(
            new MockHttpClient([
                function (string $method, string $url, array $options = []): ResponseInterface {
                    $this->assertSame('GET', $method);
                    $this->assertSame('https://localise.biz/api/export/locale/de.xlf?filter=messages&status=translated%2Cprovisional', $url);
                    $this->assertSame(['filter' => 'messages', 'status' => 'translated,provisional'], $options['query']);

                    return new MockResponse();
                },
            ], 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/',
            null,
            'translated,provisional'
        );

        $this->translatorBag = $provider->read(['messages'], ['de']);
    }

    public function testReadWithDomainMapping()
    {
        $this->loader = new XliffFileLoader();

        $provider = self::createProvider(
            new MockHttpClient([
                function (string $method, string $url, array $options): ResponseInterface {
                    $this->assertSame('foo', $options['query']['filter']);

                    return new MockResponse(<<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
                          <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                            <header>
                              <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                            </header>
                            <body>
                              <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="foo__index.hello" datatype="plaintext">
                                <source>foo__index.hello</source>
                                <target state="translated">Hello</target>
                              </trans-unit>
                            </body>
                          </file>
                        </xliff>
                        XLIFF);
                },
            ], 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/',
        );

        $this->translatorBag = $provider->read(['foo' => 'bar'], ['en']);
        $this->assertSame(['bar'], $this->translatorBag->getCatalogue('en')->getDomains());
        $this->assertSame(['index.hello' => 'Hello'], $this->translatorBag->getCatalogue('en')->all('bar'));
    }

    public function testReadWithDomainsAndFiltersMixed()
    {
        $this->loader = new XliffFileLoader();
        $filters = [];
        $provider = self::createProvider(
            new MockHttpClient(static function (string $method, string $url, array $options) use (&$filters): ResponseInterface {
                $filters[] = $options['query']['filter'];

                return new MockResponse(<<<'XLIFF'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
                      <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
                        <header>
                          <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
                        </header>
                        <body/>
                      </file>
                    </xliff>
                    XLIFF);
            }, 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/',
        );

        $this->translatorBag = $provider->read(['messages', 'foo' => 'bar'], ['en']);

        $this->assertSame(['messages', 'foo'], $filters);
    }

    public function testReadForLocaleThatDoesNotExistWarnsOnceAndReturnsAnEmptyBag()
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Locale "de" does not exist in your Loco project.');

        $provider = self::createProvider(
            new MockHttpClient(static fn (): ResponseInterface => new MockResponse('', ['http_code' => 404]), 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/',
        );

        $this->translatorBag = $provider->read(['messages', 'validators'], ['de']);

        $this->assertSame([], iterator_to_array($this->translatorBag->getCatalogues()));
    }
}
