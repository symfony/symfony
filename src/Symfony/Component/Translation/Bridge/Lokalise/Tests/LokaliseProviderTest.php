<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Lokalise\Tests;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\Bridge\Lokalise\LokaliseProvider;
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

class LokaliseProviderTest extends ProviderTestCase
{
    public static function createProvider(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint, TranslatorBagInterface $translatorBag = null): ProviderInterface
    {
        return new LokaliseProvider($client, $loader, $logger, $defaultLocale, $endpoint);
    }

    public static function toStringProvider(): iterable
    {
        yield [
            self::createProvider((new MockHttpClient())->withOptions([
                'base_uri' => 'https://api.lokalise.com/api2/projects/PROJECT_ID/',
                'headers' => ['X-Api-Token' => 'API_KEY'],
            ]), new ArrayLoader(), new NullLogger(), 'en', 'api.lokalise.com'),
            'lokalise://api.lokalise.com',
        ];

        yield [
            self::createProvider((new MockHttpClient())->withOptions([
                'base_uri' => 'https://example.com',
                'headers' => ['X-Api-Token' => 'API_KEY'],
            ]), new ArrayLoader(), new NullLogger(), 'en', 'example.com'),
            'lokalise://example.com',
        ];

        yield [
            self::createProvider((new MockHttpClient())->withOptions([
                'base_uri' => 'https://example.com:99',
                'headers' => ['X-Api-Token' => 'API_KEY'],
            ]), new ArrayLoader(), new NullLogger(), 'en', 'example.com:99'),
            'lokalise://example.com:99',
        ];
    }

    public function testCompleteWriteProcess()
    {
        $getLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);

            return new MockResponse(json_encode(['languages' => []]));
        };

        $createLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedBody = json_encode([
                'languages' => [
                    ['lang_iso' => 'en'],
                    ['lang_iso' => 'fr'],
                ],
            ]);

            $this->assertSame('POST', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse();
        };

        $getKeysIdsForMessagesDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedQuery = [
                'filter_keys' => '',
                'filter_filenames' => 'messages.xliff',
                'limit' => 5000,
                'page' => 1,
            ];

            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/keys?'.http_build_query($expectedQuery), $url);
            $this->assertSame($expectedQuery, $options['query']);

            return new MockResponse(json_encode(['keys' => []]));
        };

        $getKeysIdsForValidatorsDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedQuery = [
                'filter_keys' => '',
                'filter_filenames' => 'validators.xliff',
                'limit' => 5000,
                'page' => 1,
            ];

            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/keys?'.http_build_query($expectedQuery), $url);
            $this->assertSame($expectedQuery, $options['query']);

            return new MockResponse(json_encode(['keys' => []]));
        };

        $createKeysForMessagesDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedBody = json_encode([
                'keys' => [
                    [
                        'key_name' => 'young_dog',
                        'platforms' => ['web'],
                        'filenames' => [
                            'web' => 'messages.xliff',
                            'ios' => null,
                            'android' => null,
                            'other' => null,
                        ],
                    ],
                ],
            ]);

            $this->assertSame('POST', $method);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode(['keys' => [
                [
                    'key_name' => ['web' => 'young_dog'],
                    'key_id' => 29,
                ],
            ]]));
        };

        $createKeysForValidatorsDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedBody = json_encode([
                'keys' => [
                    [
                        'key_name' => 'post.num_comments',
                        'platforms' => ['web'],
                        'filenames' => [
                            'web' => 'validators.xliff',
                            'ios' => null,
                            'android' => null,
                            'other' => null,
                        ],
                    ],
                ],
            ]);

            $this->assertSame('POST', $method);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode(['keys' => [
                [
                    'key_name' => ['web' => 'post.num_comments'],
                    'key_id' => 92,
                ],
            ]]));
        };
        $updateProcessed = false;
        $updateTranslationsResponse = function (string $method, string $url, array $options = []) use (&$updateProcessed): ResponseInterface {
            $expectedBody = json_encode([
                'keys' => [
                    [
                        'key_id' => 29,
                        'platforms' => ['web'],
                        'filenames' => [
                            'web' => 'messages.xliff',
                            'ios' => null,
                            'android' => null,
                            'other' => null,
                        ],
                        'translations' => [
                            [
                                'language_iso' => 'en',
                                'translation' => 'puppy',
                            ],
                            [
                                'language_iso' => 'fr',
                                'translation' => 'chiot',
                            ],
                        ],
                    ],
                    [
                        'key_id' => 92,
                        'platforms' => ['web'],
                        'filenames' => [
                            'web' => 'validators.xliff',
                            'ios' => null,
                            'android' => null,
                            'other' => null,
                        ],
                        'translations' => [
                            [
                                'language_iso' => 'en',
                                'translation' => '{count, plural, one {# comment} other {# comments}}',
                            ],
                            [
                                'language_iso' => 'fr',
                                'translation' => '{count, plural, one {# commentaire} other {# commentaires}}',
                            ],
                        ],
                    ],
                ],
            ]);

            $updateProcessed = true;
            $this->assertSame('PUT', $method);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse();
        };

        $provider = self::createProvider((new MockHttpClient([
            $getLanguagesResponse,
            $createLanguagesResponse,
            $getKeysIdsForMessagesDomainResponse,
            $getKeysIdsForValidatorsDomainResponse,
            $createKeysForMessagesDomainResponse,
            $createKeysForValidatorsDomainResponse,
            $updateTranslationsResponse,
        ]))->withOptions([
            'base_uri' => 'https://api.lokalise.com/api2/projects/PROJECT_ID/',
            'headers' => ['X-Api-Token' => 'API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.lokalise.com');

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['young_dog' => 'puppy'],
            'validators' => ['post.num_comments' => '{count, plural, one {# comment} other {# comments}}'],
        ]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', [
            'messages' => ['young_dog' => 'chiot'],
            'validators' => ['post.num_comments' => '{count, plural, one {# commentaire} other {# commentaires}}'],
        ]));

        $provider->write($translatorBag);
        $this->assertTrue($updateProcessed, 'Translations update was not called.');
    }

    public function testWriteGetLanguageServerError()
    {
        $getLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);

            return new MockResponse('', ['http_code' => 500]);
        };

        $provider = self::createProvider((new MockHttpClient([
            $getLanguagesResponse,
        ]))->withOptions([
            'base_uri' => 'https://api.lokalise.com/api2/projects/PROJECT_ID/',
            'headers' => ['X-Api-Token' => 'API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.lokalise.com');

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['young_dog' => 'puppy'],
        ]));

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to get languages from Lokalise.');

        $provider->write($translatorBag);
    }

    public function testWriteCreateLanguageServerError()
    {
        $getLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);

            return new MockResponse(json_encode(['languages' => []]));
        };

        $createLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedBody = json_encode([
                'languages' => [
                    ['lang_iso' => 'en'],
                ],
            ]);

            $this->assertSame('POST', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse('', ['http_code' => 500]);
        };

        $provider = self::createProvider((new MockHttpClient([
            $getLanguagesResponse,
            $createLanguagesResponse,
        ]))->withOptions([
            'base_uri' => 'https://api.lokalise.com/api2/projects/PROJECT_ID/',
            'headers' => ['X-Api-Token' => 'API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.lokalise.com');

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['young_dog' => 'puppy'],
        ]));

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to create languages on Lokalise.');

        $provider->write($translatorBag);
    }

    public function testWriteGetKeysIdsServerError()
    {
        $getLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);

            return new MockResponse(json_encode(['languages' => []]));
        };

        $createLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedBody = json_encode([
                'languages' => [
                    ['lang_iso' => 'en'],
                ],
            ]);

            $this->assertSame('POST', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode(['keys' => []]));
        };

        $getKeysIdsForMessagesDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedQuery = [
                'filter_keys' => '',
                'filter_filenames' => 'messages.xliff',
                'limit' => 5000,
                'page' => 1,
            ];

            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/keys?'.http_build_query($expectedQuery), $url);
            $this->assertSame($expectedQuery, $options['query']);

            return new MockResponse('', ['http_code' => 500]);
        };

        $provider = self::createProvider((new MockHttpClient([
            $getLanguagesResponse,
            $createLanguagesResponse,
            $getKeysIdsForMessagesDomainResponse,
        ]))->withOptions([
            'base_uri' => 'https://api.lokalise.com/api2/projects/PROJECT_ID/',
            'headers' => ['X-Api-Token' => 'API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.lokalise.com');

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['young_dog' => 'puppy'],
        ]));

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to get keys ids from Lokalise.');

        $provider->write($translatorBag);
    }

    public function testWriteCreateKeysServerError()
    {
        $getLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);

            return new MockResponse(json_encode(['languages' => []]));
        };

        $createLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedBody = json_encode([
                'languages' => [
                    ['lang_iso' => 'en'],
                ],
            ]);

            $this->assertSame('POST', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode(['keys' => []]));
        };

        $getKeysIdsForMessagesDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedQuery = [
                'filter_keys' => '',
                'filter_filenames' => 'messages.xliff',
                'limit' => 5000,
                'page' => 1,
            ];

            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/keys?'.http_build_query($expectedQuery), $url);
            $this->assertSame($expectedQuery, $options['query']);

            return new MockResponse(json_encode(['keys' => []]));
        };

        $createKeysForMessagesDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedBody = json_encode([
                'keys' => [
                    [
                        'key_name' => 'young_dog',
                        'platforms' => ['web'],
                        'filenames' => [
                            'web' => 'messages.xliff',
                            'ios' => null,
                            'android' => null,
                            'other' => null,
                        ],
                    ],
                ],
            ]);

            $this->assertSame('POST', $method);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse('', ['http_code' => 500]);
        };

        $provider = self::createProvider((new MockHttpClient([
            $getLanguagesResponse,
            $createLanguagesResponse,
            $getKeysIdsForMessagesDomainResponse,
            $createKeysForMessagesDomainResponse,
        ]))->withOptions([
            'base_uri' => 'https://api.lokalise.com/api2/projects/PROJECT_ID/',
            'headers' => ['X-Api-Token' => 'API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.lokalise.com');

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['young_dog' => 'puppy'],
        ]));

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to create keys to Lokalise.');

        $provider->write($translatorBag);
    }

    public function testWriteUploadTranslationsServerError()
    {
        $getLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);

            return new MockResponse(json_encode(['languages' => []]));
        };

        $createLanguagesResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedBody = json_encode([
                'languages' => [
                    ['lang_iso' => 'en'],
                ],
            ]);

            $this->assertSame('POST', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/languages', $url);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode(['keys' => []]));
        };

        $getKeysIdsForMessagesDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedQuery = [
                'filter_keys' => '',
                'filter_filenames' => 'messages.xliff',
                'limit' => 5000,
                'page' => 1,
            ];

            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/keys?'.http_build_query($expectedQuery), $url);
            $this->assertSame($expectedQuery, $options['query']);

            return new MockResponse(json_encode(['keys' => []]));
        };

        $createKeysForMessagesDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedBody = json_encode([
                'keys' => [
                    [
                        'key_name' => 'young_dog',
                        'platforms' => ['web'],
                        'filenames' => [
                            'web' => 'messages.xliff',
                            'ios' => null,
                            'android' => null,
                            'other' => null,
                        ],
                    ],
                ],
            ]);

            $this->assertSame('POST', $method);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode(['keys' => [
                [
                    'key_name' => ['web' => 'young_dog'],
                    'key_id' => 29,
                ],
            ]]));
        };

        $updateTranslationsResponse = function (string $method, string $url, array $options = []) use (&$updateProcessed): ResponseInterface {
            $this->assertSame('PUT', $method);

            return new MockResponse('', ['http_code' => 500]);
        };

        $provider = self::createProvider((new MockHttpClient([
            $getLanguagesResponse,
            $createLanguagesResponse,
            $getKeysIdsForMessagesDomainResponse,
            $createKeysForMessagesDomainResponse,
            $updateTranslationsResponse,
        ]))->withOptions([
            'base_uri' => 'https://api.lokalise.com/api2/projects/PROJECT_ID/',
            'headers' => ['X-Api-Token' => 'API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.lokalise.com');

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['young_dog' => 'puppy'],
        ]));

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to create/update translations to Lokalise.');

        $provider->write($translatorBag);
    }

    /**
     * @dataProvider getResponsesForOneLocaleAndOneDomain
     */
    public function testReadForOneLocaleAndOneDomain(string $locale, string $domain, string $responseContent, TranslatorBag $expectedTranslatorBag)
    {
        $response = function (string $method, string $url, array $options = []) use ($locale, $domain, $responseContent): ResponseInterface {
            $expectedBody = json_encode([
                'format' => 'symfony_xliff',
                'original_filenames' => true,
                'directory_prefix' => '%LANG_ISO%',
                'filter_langs' => [$locale],
                'filter_filenames' => [$domain.'.xliff'],
                'export_empty_as' => 'skip',
                'replace_breaks' => false,
            ]);

            $this->assertSame('POST', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/files/export', $url);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode([
                'files' => [
                    $locale => [
                        $domain.'.xliff' => [
                            'content' => $responseContent,
                        ],
                    ],
                ],
            ]));
        };

        $loader = $this->getLoader();
        $loader->expects($this->once())
            ->method('load')
            ->willReturn((new XliffFileLoader())->load($responseContent, $locale, $domain));

        $provider = self::createProvider((new MockHttpClient($response))->withOptions([
            'base_uri' => 'https://api.lokalise.com/api2/projects/PROJECT_ID/',
            'headers' => ['X-Api-Token' => 'API_KEY'],
        ]), $loader, $this->getLogger(), $this->getDefaultLocale(), 'api.lokalise.com');
        $translatorBag = $provider->read([$domain], [$locale]);

        // We don't want to assert equality of metadata here, due to the ArrayLoader usage.
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $catalogue->deleteMetadata('', '');
        }

        $this->assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
    }

    /**
     * @dataProvider getResponsesForManyLocalesAndManyDomains
     */
    public function testReadForManyLocalesAndManyDomains(array $locales, array $domains, array $responseContents, TranslatorBag $expectedTranslatorBag)
    {
        $consecutiveLoadArguments = [];
        $consecutiveLoadReturns = [];
        $response = new MockResponse(json_encode([
            'files' => array_reduce($locales, function ($carry, $locale) use ($domains, $responseContents, &$consecutiveLoadArguments, &$consecutiveLoadReturns) {
                $carry[$locale] = array_reduce($domains, function ($carry, $domain) use ($locale, $responseContents, &$consecutiveLoadArguments, &$consecutiveLoadReturns) {
                    $carry[$domain.'.xliff'] = [
                        'content' => $responseContents[$locale][$domain],
                    ];

                    $consecutiveLoadArguments[] = [$responseContents[$locale][$domain], $locale, $domain];
                    $consecutiveLoadReturns[] = (new XliffFileLoader())->load($responseContents[$locale][$domain], $locale, $domain);

                    return $carry;
                }, []);

                return $carry;
            }, []),
        ]));

        $loader = $this->getLoader();
        $loader->expects($this->exactly(\count($consecutiveLoadArguments)))
            ->method('load')
            ->willReturnCallback(function (...$args) use (&$consecutiveLoadArguments, &$consecutiveLoadReturns) {
                $this->assertSame(array_shift($consecutiveLoadArguments), $args);

                return array_shift($consecutiveLoadReturns);
            });

        $provider = self::createProvider((new MockHttpClient($response))->withOptions([
            'base_uri' => 'https://api.lokalise.com/api2/projects/PROJECT_ID/',
            'headers' => ['X-Api-Token' => 'API_KEY'],
        ]), $loader, $this->getLogger(), $this->getDefaultLocale(), 'api.lokalise.com');

        $translatorBag = $provider->read($domains, $locales);
        // We don't want to assert equality of metadata here, due to the ArrayLoader usage.
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $catalogue->deleteMetadata('', '');
        }

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $this->assertEquals($expectedTranslatorBag->getCatalogue($locale)->all($domain), $translatorBag->getCatalogue($locale)->all($domain));
            }
        }
    }

    public function testDeleteProcess()
    {
        $getKeysIdsForMessagesDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedQuery = [
                'filter_keys' => 'a',
                'filter_filenames' => 'messages.xliff',
                'limit' => 5000,
                'page' => 1,
            ];

            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/keys?'.http_build_query($expectedQuery), $url);
            $this->assertSame($expectedQuery, $options['query']);

            return new MockResponse(json_encode(['keys' => [
                [
                    'key_name' => ['web' => 'a'],
                    'key_id' => 29,
                ],
            ]]));
        };

        $getKeysIdsForValidatorsDomainResponse = function (string $method, string $url, array $options = []): ResponseInterface {
            $expectedQuery = [
                'filter_keys' => 'post.num_comments',
                'filter_filenames' => 'validators.xliff',
                'limit' => 5000,
                'page' => 1,
            ];

            $this->assertSame('GET', $method);
            $this->assertSame('https://api.lokalise.com/api2/projects/PROJECT_ID/keys?'.http_build_query($expectedQuery), $url);
            $this->assertSame($expectedQuery, $options['query']);

            return new MockResponse(json_encode(['keys' => [
                [
                    'key_name' => ['web' => 'post.num_comments'],
                    'key_id' => 92,
                ],
            ]]));
        };

        $deleteResponse = function (string $method, string $url, array $options = []): MockResponse {
            $this->assertSame('DELETE', $method);
            $this->assertSame(json_encode(['keys' => [29, 92]]), $options['body']);

            return new MockResponse();
        };

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# comment} other {# comments}}'],
            'domain_without_missing_messages' => [],
        ]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', [
            'messages' => ['a' => 'trans_fr_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# commentaire} other {# commentaires}}'],
            'domain_without_missing_messages' => [],
        ]));

        $provider = self::createProvider(
            new MockHttpClient([
                $getKeysIdsForMessagesDomainResponse,
                $getKeysIdsForValidatorsDomainResponse,
                $deleteResponse,
            ], 'https://api.lokalise.com/api2/projects/PROJECT_ID/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.lokalise.com'
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
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
  <file original="" datatype="plaintext" xml:space="preserve" source-language="en" target-language="en">
    <header>
      <tool tool-id="lokalise.com" tool-name="Lokalise"/>
    </header>
    <body>
      <trans-unit id="index.greetings" resname="index.greetings">
        <source>index.greetings</source>
        <target>Welcome, {firstname}!</target>
      </trans-unit>
      <trans-unit id="index.hello" resname="index.hello">
        <source>index.hello</source>
        <target>Hello</target>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
            ,
            $expectedTranslatorBagEn,
        ];

        $expectedTranslatorBagFr = new TranslatorBag();
        $expectedTranslatorBagFr->addCatalogue($arrayLoader->load([
            'index.hello' => 'Bonjour',
            'index.greetings' => 'Bienvenue, {firstname} !',
        ], 'fr'));

        yield ['fr', 'messages', <<<'XLIFF'
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
  <file original="" datatype="plaintext" xml:space="preserve" source-language="en" target-language="fr">
    <header>
      <tool tool-id="lokalise.com" tool-name="Lokalise"/>
    </header>
    <body>
      <trans-unit id="index.greetings" resname="index.greetings">
        <source>index.greetings</source>
        <target>Bienvenue, {firstname} !</target>
      </trans-unit>
      <trans-unit id="index.hello" resname="index.hello">
        <source>index.hello</source>
        <target>Bonjour</target>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
            ,
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
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
  <file original="" datatype="plaintext" xml:space="preserve" source-language="en" target-language="en">
    <header>
      <tool tool-id="lokalise.com" tool-name="Lokalise"/>
    </header>
    <body>
      <trans-unit id="index.greetings" resname="index.greetings">
        <source>index.greetings</source>
        <target>Welcome, {firstname}!</target>
      </trans-unit>
      <trans-unit id="index.hello" resname="index.hello">
        <source>index.hello</source>
        <target>Hello</target>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
                    ,
                    'validators' => <<<'XLIFF'
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
  <file original="" datatype="plaintext" xml:space="preserve" source-language="en" target-language="en">
    <header>
      <tool tool-id="lokalise.com" tool-name="Lokalise"/>
    </header>
    <body>
      <trans-unit id="lastname.error" resname="lastname.error">
        <source>lastname.error</source>
        <target>Lastname must contains only letters.</target>
      </trans-unit>
      <trans-unit id="firstname.error" resname="firstname.error">
        <source>firstname.error</source>
        <target>Firstname must contains only letters.</target>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
                    ,
                ],
                'fr' => [
                    'messages' => <<<'XLIFF'
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
  <file original="" datatype="plaintext" xml:space="preserve" source-language="en" target-language="fr">
    <header>
      <tool tool-id="lokalise.com" tool-name="Lokalise"/>
    </header>
    <body>
      <trans-unit id="index.greetings" resname="index.greetings">
        <source>index.greetings</source>
        <target>Bienvenue, {firstname} !</target>
      </trans-unit>
      <trans-unit id="index.hello" resname="index.hello">
        <source>index.hello</source>
        <target>Bonjour</target>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
                    ,
                    'validators' => <<<'XLIFF'
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
  <file original="" datatype="plaintext" xml:space="preserve" source-language="en" target-language="fr">
    <header>
      <tool tool-id="lokalise.com" tool-name="Lokalise"/>
    </header>
    <body>
      <trans-unit id="lastname.error" resname="lastname.error">
        <source>lastname.error</source>
        <target>Le nom de famille ne peut contenir que des lettres.</target>
      </trans-unit>
      <trans-unit id="firstname.error" resname="firstname.error">
        <source>firstname.error</source>
        <target>Le prénom ne peut contenir que des lettres.</target>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
                    ,
                ],
            ],
            $expectedTranslatorBag,
        ];
    }
}
