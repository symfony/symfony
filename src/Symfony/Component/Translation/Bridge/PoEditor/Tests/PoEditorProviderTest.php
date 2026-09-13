<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\PoEditor\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\Bridge\PoEditor\PoEditorHttpClient;
use Symfony\Component\Translation\Bridge\PoEditor\PoEditorProvider;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Test\ProviderTestCase;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PoEditorProviderTest extends ProviderTestCase
{
    public static function createProvider(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint): ProviderInterface
    {
        return new PoEditorProvider(PoEditorHttpClient::create($client, 'https://'.$endpoint.'/v2/', 'API_KEY', 'PROJECT_ID'), $loader, $logger, $defaultLocale, $endpoint);
    }

    public static function toStringProvider(): iterable
    {
        $client = new MockHttpClient();
        $loader = new ArrayLoader();
        $logger = new NullLogger();

        yield [
            self::createProvider($client, $loader, $logger, 'en', 'api.poeditor.com'),
            'poeditor://api.poeditor.com',
        ];

        yield [
            self::createProvider($client, $loader, $logger, 'en', 'example.com'),
            'poeditor://example.com',
        ];

        yield [
            self::createProvider($client, $loader, $logger, 'en', 'example.com:99'),
            'poeditor://example.com:99',
        ];
    }

    public function testCompleteWriteProcess()
    {
        $successResponse = new JsonMockResponse([
            'response' => [
                'status' => 'success',
                'code' => '200',
                'message' => 'OK',
            ],
        ]);

        $responses = [
            'listLanguages' => function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.poeditor.com/v2/languages/list', $url);
                $this->assertSame(http_build_query([
                    'api_token' => 'API_KEY',
                    'id' => 'PROJECT_ID',
                ]), $options['body']);

                return self::createSuccessResponse(['languages' => [['code' => 'en'], ['code' => 'fr']]]);
            },
            'addTerms' => function (string $method, string $url, array $options = []) use ($successResponse): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame(http_build_query([
                    'api_token' => 'API_KEY',
                    'id' => 'PROJECT_ID',
                    'data' => json_encode([
                        [
                            'term' => 'a',
                            'reference' => 'a',
                            'tags' => ['messages'],
                            'context' => 'messages',
                        ],
                        [
                            'term' => 'post.num_comments',
                            'reference' => 'post.num_comments',
                            'tags' => ['validators'],
                            'context' => 'validators',
                        ],
                    ]),
                ]), $options['body']);

                return $successResponse;
            },
            'addTranslationsEn' => function (string $method, string $url, array $options = []) use ($successResponse): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame(http_build_query([
                    'api_token' => 'API_KEY',
                    'id' => 'PROJECT_ID',
                    'language' => 'en',
                    'data' => json_encode([
                        [
                            'term' => 'a',
                            'context' => 'messages',
                            'translation' => ['content' => 'trans_en_a'],
                        ],
                        [
                            'term' => 'post.num_comments',
                            'context' => 'validators',
                            'translation' => ['content' => '{count, plural, one {# comment} other {# comments}}'],
                        ],
                    ]),
                ]), $options['body']);

                return $successResponse;
            },
            'addTranslationsFr' => function (string $method, string $url, array $options = []) use ($successResponse): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame(http_build_query([
                    'api_token' => 'API_KEY',
                    'id' => 'PROJECT_ID',
                    'language' => 'fr',
                    'data' => json_encode([
                        [
                            'term' => 'a',
                            'context' => 'messages',
                            'translation' => ['content' => 'trans_fr_a'],
                        ],
                        [
                            'term' => 'post.num_comments',
                            'context' => 'validators',
                            'translation' => ['content' => '{count, plural, one {# commentaire} other {# commentaires}}'],
                        ],
                    ]),
                ]), $options['body']);

                return $successResponse;
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

        $provider = self::createProvider(
            $httpClient = new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $provider->write($translatorBag);

        $this->assertSame(\count($responses), $httpClient->getRequestsCount());
    }

    public function testWriteAddsMissingLanguages()
    {
        $addLanguage = fn (string $language) => function (string $method, string $url, array $options = []) use ($language): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.poeditor.com/v2/languages/add', $url);
            $this->assertSame(http_build_query([
                'api_token' => 'API_KEY',
                'id' => 'PROJECT_ID',
                'language' => $language,
            ]), $options['body']);

            return self::createSuccessResponse();
        };

        $responses = [
            'listLanguages' => self::createSuccessResponse(['languages' => [['code' => 'en']]]),
            'addLanguageFr' => $addLanguage('fr'),
            'addLanguageDe' => $addLanguage('de'),
            'addTerms' => self::createSuccessResponse(),
            'addTranslationsEn' => self::createSuccessResponse(),
            'addTranslationsFr' => self::createSuccessResponse(),
            'addTranslationsDe' => self::createSuccessResponse(),
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', ['messages' => ['a' => 'trans_en_a']]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', ['messages' => ['a' => 'trans_fr_a']]));
        $translatorBag->addCatalogue(new MessageCatalogue('de', ['messages' => ['a' => 'trans_de_a']]));

        $provider = self::createProvider(
            $httpClient = new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $provider->write($translatorBag);

        $this->assertSame(\count($responses), $httpClient->getRequestsCount());
    }

    public function testWriteLogsLanguageThatCannotBeAdded()
    {
        $responses = [
            'listLanguages' => self::createSuccessResponse(['languages' => [['code' => 'en']]]),
            'addLanguageFr' => new JsonMockResponse([
                'response' => [
                    'status' => 'fail',
                    'code' => '4043',
                    'message' => 'Wrong language code',
                ],
            ]),
            'addTerms' => self::createSuccessResponse(),
            'addTranslationsEn' => self::createSuccessResponse(),
            'addTranslationsFr' => self::createSuccessResponse(),
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', ['messages' => ['a' => 'trans_en_a']]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', ['messages' => ['a' => 'trans_fr_a']]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringStartsWith('Unable to add the "fr" language to POEditor: '));

        $provider = self::createProvider(
            $httpClient = new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            $this->getLoader(),
            $logger,
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $provider->write($translatorBag);

        $this->assertSame(\count($responses), $httpClient->getRequestsCount());
    }

    public function testWriteUsesThePoEditorLanguageCodes()
    {
        $requests = [];
        $client = static function (string $method, string $url, array $options = []) use (&$requests): MockResponse {
            parse_str($options['body'], $body);
            $requests[] = rtrim(substr($url, \strlen('https://api.poeditor.com/v2/')).' '.($body['language'] ?? ''));

            return self::createSuccessResponse(['languages' => [['code' => 'en'], ['code' => 'pt-br'], ['code' => 'zh-Hans']]]);
        };

        $translatorBag = new TranslatorBag();
        foreach (['en', 'pt_BR', 'zh_Hans', 'de_AT'] as $locale) {
            $translatorBag->addCatalogue(new MessageCatalogue($locale, ['messages' => ['a' => 'trans_a']]));
        }

        $provider = self::createProvider(
            new MockHttpClient($client, 'https://api.poeditor.com/v2/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $provider->write($translatorBag);

        $this->assertSame([
            'languages/list',
            'languages/add de-at',
            'terms/add',
            'translations/add en',
            'translations/add pt-br',
            'translations/add zh-Hans',
            'translations/add de-at',
        ], $requests);
    }

    #[DataProvider('getResponsesForOneLocaleAndOneDomain')]
    public function testReadForOneLocaleAndOneDomain(string $locale, string $domain, string $responseContent, TranslatorBag $expectedTranslatorBag)
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())
            ->method('load')
            ->willReturn((new XliffFileLoader())->load($responseContent, $locale, $domain));

        $responses = [
            self::createSuccessResponse(['languages' => [['code' => $locale]]]),
            new JsonMockResponse([
                'response' => [
                    'status' => 'success',
                    'code' => '200',
                    'message' => 'OK',
                ],
                'result' => [
                    'url' => 'https://api.poeditor.com/v2/download/file/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                ],
            ]),
            new MockResponse($responseContent),
        ];

        $provider = self::createProvider(
            new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            $loader,
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

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
        $listLanguages = self::createSuccessResponse(['languages' => array_map(static fn (string $locale) => ['code' => $locale], $locales)]);
        $exportResponses = $downloadResponses = [];
        $consecutiveLoadArguments = [];
        $consecutiveLoadReturns = [];
        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $exportResponses[] = new JsonMockResponse([
                    'response' => [
                        'status' => 'success',
                        'code' => '200',
                        'message' => 'OK',
                    ],
                    'result' => [
                        'url' => 'https://api.poeditor.com/v2/download/file/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                    ],
                ]);
                $downloadResponses[] = new MockResponse($responseContents[$locale][$domain]);
                $consecutiveLoadArguments[] = [$responseContents[$locale][$domain], $locale, $domain];
                $consecutiveLoadReturns[] = (new XliffFileLoader())->load($responseContents[$locale][$domain], $locale, $domain);
            }
        }

        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->exactly(\count($consecutiveLoadArguments)))
            ->method('load')
            ->willReturnCallback(function (...$args) use (&$consecutiveLoadArguments, &$consecutiveLoadReturns) {
                $this->assertSame(array_shift($consecutiveLoadArguments), $args);

                return array_shift($consecutiveLoadReturns);
            });

        $provider = self::createProvider(
            new MockHttpClient([$listLanguages, ...$exportResponses, ...$downloadResponses], 'https://api.poeditor.com/v2/'),
            $loader,
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $translatorBag = $provider->read($domains, $locales);
        // We don't want to assert equality of metadata here, due to the ArrayLoader usage.
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $catalogue->deleteMetadata('', '');
        }

        $this->assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
    }

    public function testReadWithoutLocales()
    {
        $export = fn (string $locale) => function (string $method, string $url, array $options = []) use ($locale): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.poeditor.com/v2/projects/export', $url);
            $this->assertSame(http_build_query([
                'api_token' => 'API_KEY',
                'id' => 'PROJECT_ID',
                'language' => $locale,
                'type' => 'xlf',
                'filters' => json_encode(['translated']),
                'tags' => json_encode(['messages']),
            ]), $options['body']);

            return self::createSuccessResponse(['url' => 'https://api.poeditor.com/v2/download/file/'.$locale]);
        };

        $responses = [
            'listLanguages' => function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.poeditor.com/v2/languages/list', $url);

                return self::createSuccessResponse(['languages' => [['code' => 'en'], ['code' => 'fr']]]);
            },
            'exportEn' => $export('en'),
            'exportFr' => $export('fr'),
            'downloadEn' => self::createXliffResponse('en', 'index.hello', 'Hello'),
            'downloadFr' => self::createXliffResponse('fr', 'index.hello', 'Bonjour'),
        ];

        $provider = self::createProvider(
            $httpClient = new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            new XliffFileLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $translatorBag = $provider->read(['messages'], []);

        $this->assertSame(['en', 'fr'], array_map(static fn (MessageCatalogue $catalogue) => $catalogue->getLocale(), $translatorBag->getCatalogues()));
        $this->assertSame(['index.hello' => 'Bonjour'], $translatorBag->getCatalogue('fr')->all('messages'));
        $this->assertSame(\count($responses), $httpClient->getRequestsCount());
    }

    public function testReadWithoutLocalesNamesTheCataloguesAfterTheSymfonyLocales()
    {
        $export = fn (string $language) => function (string $method, string $url, array $options = []) use ($language): MockResponse {
            $this->assertSame('https://api.poeditor.com/v2/projects/export', $url);
            $this->assertStringContainsString('&language='.$language.'&', $options['body']);

            return self::createSuccessResponse(['url' => 'https://api.poeditor.com/v2/download/file/'.$language]);
        };

        $responses = [
            'listLanguages' => self::createSuccessResponse(['languages' => [['code' => 'pt-br'], ['code' => 'zh-Hans'], ['code' => 'sr-cyrl']]]),
            'exportPtBr' => $export('pt-br'),
            'exportZhHans' => $export('zh-Hans'),
            'exportSrCyrl' => $export('sr-cyrl'),
            'downloadPtBr' => self::createXliffResponse('pt-br', 'index.hello', 'Ola'),
            'downloadZhHans' => self::createXliffResponse('zh-Hans', 'index.hello', 'Nihao'),
            'downloadSrCyrl' => self::createXliffResponse('sr-cyrl', 'index.hello', 'Zdravo'),
        ];

        $provider = self::createProvider(
            $httpClient = new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            new XliffFileLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $translatorBag = $provider->read(['messages'], []);

        $this->assertSame(['pt_BR', 'zh_Hans', 'sr_Cyrl'], array_map(static fn (MessageCatalogue $catalogue) => $catalogue->getLocale(), $translatorBag->getCatalogues()));
        $this->assertSame(['index.hello' => 'Ola'], $translatorBag->getCatalogue('pt_BR')->all('messages'));
        $this->assertSame(\count($responses), $httpClient->getRequestsCount());
    }

    public function testReadSendsThePoEditorLanguageCodeAndKeepsTheRequestedLocale()
    {
        $requests = [];
        $client = static function (string $method, string $url, array $options = []) use (&$requests): MockResponse {
            $endpoint = substr($url, \strlen('https://api.poeditor.com/v2/'));

            if (str_starts_with($endpoint, 'download/')) {
                return self::createXliffResponse('pt-br', 'index.hello', 'Ola');
            }

            parse_str($options['body'], $body);
            $requests[] = rtrim($endpoint.' '.($body['language'] ?? ''));

            return self::createSuccessResponse('languages/list' === $endpoint
                ? ['languages' => [['code' => 'en'], ['code' => 'pt-br']]]
                : ['url' => 'https://api.poeditor.com/v2/download/file/pt-br']
            );
        };

        $provider = self::createProvider(
            new MockHttpClient($client, 'https://api.poeditor.com/v2/'),
            new XliffFileLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $translatorBag = $provider->read(['messages'], ['pt_BR']);

        $this->assertSame(['languages/list', 'projects/export pt-br'], $requests);
        $this->assertSame(['index.hello' => 'Ola'], $translatorBag->getCatalogue('pt_BR')->all('messages'));
    }

    public function testReadWithoutDomains()
    {
        $export = fn (string $domain) => function (string $method, string $url, array $options = []) use ($domain): MockResponse {
            $this->assertSame('https://api.poeditor.com/v2/projects/export', $url);
            $this->assertStringEndsWith('&tags='.urlencode(json_encode([$domain])), $options['body']);

            return self::createSuccessResponse(['url' => 'https://api.poeditor.com/v2/download/file/'.$domain]);
        };

        $responses = [
            'listTerms' => function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.poeditor.com/v2/terms/list', $url);
                $this->assertSame(http_build_query([
                    'api_token' => 'API_KEY',
                    'id' => 'PROJECT_ID',
                ]), $options['body']);

                return self::createSuccessResponse(['terms' => [
                    ['term' => 'index.hello', 'context' => 'messages', 'tags' => ['messages']],
                    ['term' => 'firstname.error', 'context' => 'validators', 'tags' => ['validators']],
                    ['term' => 'index.greetings', 'context' => 'messages', 'tags' => ['messages']],
                    ['term' => 'no.tag', 'context' => 'messages', 'tags' => []],
                ]]);
            },
            'listLanguages' => self::createSuccessResponse(['languages' => [['code' => 'en']]]),
            'exportMessages' => $export('messages'),
            'exportValidators' => $export('validators'),
            'downloadMessages' => self::createXliffResponse('en', 'index.hello', 'Hello'),
            'downloadValidators' => self::createXliffResponse('en', 'firstname.error', 'Firstname must contains only letters.'),
        ];

        $provider = self::createProvider(
            $httpClient = new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            new XliffFileLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $translatorBag = $provider->read([], ['en']);

        $this->assertEqualsCanonicalizing(['messages', 'validators'], $translatorBag->getCatalogue('en')->getDomains());
        $this->assertSame(\count($responses), $httpClient->getRequestsCount());
    }

    public function testReadWithoutDomainsUsesTheTagsOfTheTerms()
    {
        $export = fn (string $domain) => function (string $method, string $url, array $options = []) use ($domain): MockResponse {
            $this->assertSame('https://api.poeditor.com/v2/projects/export', $url);
            $this->assertStringEndsWith('&tags='.urlencode(json_encode([$domain])), $options['body']);

            return self::createSuccessResponse(['url' => 'https://api.poeditor.com/v2/download/file/'.$domain]);
        };

        $responses = [
            'listTerms' => self::createSuccessResponse(['terms' => [
                ['term' => 'index.hello', 'context' => '', 'tags' => ['messages']],
                ['term' => 'firstname.error', 'context' => '', 'tags' => ['validators', 'messages']],
                ['term' => 'no.tag', 'context' => '', 'tags' => []],
            ]]),
            'listLanguages' => self::createSuccessResponse(['languages' => [['code' => 'en']]]),
            'exportMessages' => $export('messages'),
            'exportValidators' => $export('validators'),
            'downloadMessages' => self::createXliffResponse('en', 'index.hello', 'Hello'),
            'downloadValidators' => self::createXliffResponse('en', 'firstname.error', 'Firstname must contains only letters.'),
        ];

        $provider = self::createProvider(
            $httpClient = new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            new XliffFileLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $translatorBag = $provider->read([], ['en']);

        $this->assertEqualsCanonicalizing(['messages', 'validators'], $translatorBag->getCatalogue('en')->getDomains());
        $this->assertSame(\count($responses), $httpClient->getRequestsCount());
    }

    public function testReadWithoutDomainsAndLocalesFromAnEmptyProject()
    {
        $responses = [
            'listTerms' => self::createSuccessResponse(['terms' => []]),
            'listLanguages' => self::createSuccessResponse(['languages' => []]),
        ];

        $provider = self::createProvider(
            $httpClient = new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $this->assertSame([], $provider->read([], [])->getCatalogues());
        $this->assertSame(\count($responses), $httpClient->getRequestsCount());
    }

    #[TestWith(['terms/list', 'Unable to list the translation keys on POEditor: '])]
    #[TestWith(['languages/list', 'Unable to list the languages on POEditor: '])]
    public function testReadWithoutDomainsAndLocalesThrowsWhenTheProjectCannotBeListed(string $failingEndpoint, string $expectedMessage)
    {
        $provider = self::createProvider(
            new MockHttpClient(static function (string $method, string $url) use ($failingEndpoint): MockResponse {
                if (str_ends_with($url, $failingEndpoint)) {
                    return new JsonMockResponse([
                        'response' => [
                            'status' => 'fail',
                            'code' => '4011',
                            'message' => 'Invalid API Token',
                        ],
                    ]);
                }

                return self::createSuccessResponse(['terms' => [['term' => 'a', 'tags' => ['messages']]]]);
            }, 'https://api.poeditor.com/v2/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage($expectedMessage);

        $provider->read([], []);
    }

    public function testDeleteProcess()
    {
        $successResponse = new JsonMockResponse([
            'response' => [
                'status' => 'success',
                'code' => '200',
                'message' => 'OK',
            ],
        ]);

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
            new MockHttpClient(function (string $method, string $url, array $options = []) use ($successResponse): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame(http_build_query([
                    'api_token' => 'API_KEY',
                    'id' => 'PROJECT_ID',
                    'data' => json_encode([
                        [
                            'term' => 'a',
                            'context' => 'messages',
                        ],
                        [
                            'term' => 'post.num_comments',
                            'context' => 'validators',
                        ],
                    ]),
                ]), $options['body']);

                return $successResponse;
            }, 'https://api.poeditor.com/v2/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $provider->delete($translatorBag);
    }

    public static function getResponsesForOneLocaleAndOneDomain(): \Generator
    {
        $arrayLoader = new ArrayLoader();

        $expectedTranslatorBagEn = new TranslatorBag();
        $expectedTranslatorBagEn->addCatalogue($arrayLoader->load([
            'index.hello' => 'Hello',
            'index.greetings' => 'Welcome, {firstname}!',
        ], 'en'));

        yield ['en', 'messages', <<<'XLIFF'
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="ng2.template">
                <body>
                  <trans-unit id="hRlpU4" resname="index.hello">
                    <source>index.hello</source>
                    <target>Hello</target>
                  </trans-unit>
                  <trans-unit id="d3x9Aq" resname="index.greetings">
                    <source>index.greetings</source>
                    <target>Welcome, {firstname}!</target>
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
        ], 'fr'));

        yield ['fr', 'messages', <<<'XLIFF'
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
              <file source-language="en" target-language="fr" datatype="plaintext" original="ng2.template">
                <body>
                  <trans-unit id="hRlpU4" resname="index.hello">
                    <source>index.hello</source>
                    <target>Bonjour</target>
                  </trans-unit>
                  <trans-unit id="d3x9Aq" resname="index.greetings">
                    <source>index.greetings</source>
                    <target>Bienvenue, {firstname} !</target>
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
            'index.greetings' => 'Welcome, {firstname}!',
        ], 'en'));
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'index.hello' => 'Bonjour',
            'index.greetings' => 'Bienvenue, {firstname} !',
        ], 'fr'));
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
            ['messages', 'validators'],
            [
                'en' => [
                    'messages' => <<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
                          <file source-language="en" target-language="en" datatype="plaintext" original="ng2.template">
                            <body>
                              <trans-unit id="hRlpU4" resname="index.hello">
                                <source>index.hello</source>
                                <target>Hello</target>
                              </trans-unit>
                              <trans-unit id="d3x9Aq" resname="index.greetings">
                                <source>index.greetings</source>
                                <target>Welcome, {firstname}!</target>
                              </trans-unit>
                            </body>
                          </file>
                        </xliff>
                        XLIFF,
                    'validators' => <<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
                          <file source-language="en" target-language="en" datatype="plaintext" original="ng2.template">
                            <body>
                              <trans-unit id="fN3s7p" resname="firstname.error">
                                <source>firstname.error</source>
                                <target>Firstname must contains only letters.</target>
                              </trans-unit>
                              <trans-unit id="lN8x2w" resname="lastname.error">
                                <source>lastname.error</source>
                                <target>Lastname must contains only letters.</target>
                              </trans-unit>
                            </body>
                          </file>
                        </xliff>
                        XLIFF,
                ],
                'fr' => [
                    'messages' => <<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
                          <file source-language="en" target-language="fr" datatype="plaintext" original="ng2.template">
                            <body>
                              <trans-unit id="hRlpU4" resname="index.hello">
                                <source>index.hello</source>
                                <target>Bonjour</target>
                              </trans-unit>
                              <trans-unit id="d3x9Aq" resname="index.greetings">
                                <source>index.greetings</source>
                                <target>Bienvenue, {firstname} !</target>
                              </trans-unit>
                            </body>
                          </file>
                        </xliff>
                        XLIFF,
                    'validators' => <<<'XLIFF'
                        <?xml version="1.0" encoding="UTF-8"?>
                        <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
                          <file source-language="en" target-language="fr" datatype="plaintext" original="ng2.template">
                            <body>
                              <trans-unit id="fN3s7p" resname="firstname.error">
                                <source>firstname.error</source>
                                <target>Le prénom ne peut contenir que des lettres.</target>
                              </trans-unit>
                              <trans-unit id="lN8x2w" resname="lastname.error">
                                <source>lastname.error</source>
                                <target>Le nom de famille ne peut contenir que des lettres.</target>
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

    private static function createSuccessResponse(array $result = []): JsonMockResponse
    {
        $body = [
            'response' => [
                'status' => 'success',
                'code' => '200',
                'message' => 'OK',
            ],
        ];

        if ($result) {
            $body['result'] = $result;
        }

        return new JsonMockResponse($body);
    }

    private static function createXliffResponse(string $locale, string $id, string $translation): MockResponse
    {
        return new MockResponse(<<<XLIFF
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
              <file source-language="en" target-language="$locale" datatype="plaintext" original="ng2.template">
                <body>
                  <trans-unit id="$id" resname="$id">
                    <source>$id</source>
                    <target>$translation</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>
            XLIFF);
    }
}
