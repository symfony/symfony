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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\Bridge\PoEditor\PoEditorHttpClient;
use Symfony\Component\Translation\Bridge\PoEditor\PoEditorProvider;
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
            new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $provider->write($translatorBag);
    }

    #[DataProvider('getResponsesForOneLocaleAndOneDomain')]
    public function testReadForOneLocaleAndOneDomain(string $locale, string $domain, string $responseContent, TranslatorBag $expectedTranslatorBag)
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())
            ->method('load')
            ->willReturn((new XliffFileLoader())->load($responseContent, $locale, $domain));

        $responses = [
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
            new MockHttpClient(array_merge($exportResponses, $downloadResponses), 'https://api.poeditor.com/v2/'),
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
}
