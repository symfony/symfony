<?php

namespace Symfony\Component\Translation\Bridge\PoEditor\Tests;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
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
    public function createProvider(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint): ProviderInterface
    {
        return new PoEditorProvider(PoEditorHttpClient::create($client, 'https://poeditor', 'API_KEY', 'PROJECT_ID'), $loader, $logger, $defaultLocale, $endpoint);
    }

    public function toStringProvider(): iterable
    {
        $client = PoEditorHttpClient::create($this->getClient(), 'https://poeditor', 'API_KEY', 'PROJECT_ID');

        yield [
            $this->createProvider($client, $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.poeditor.com'),
            'poeditor://api.poeditor.com',
        ];

        yield [
            $this->createProvider($client, $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'example.com'),
            'poeditor://example.com',
        ];

        yield [
            $this->createProvider($client, $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'example.com:99'),
            'poeditor://example.com:99',
        ];
    }

    public function testCompleteWriteProcess()
    {
        $successResponse = new MockResponse(json_encode([
            'response' => [
                'status' => 'success',
                'code' => '200',
                'message' => 'OK',
            ],
        ]));

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

        $provider = $this->createProvider(
            new MockHttpClient($responses, 'https://api.poeditor.com/v2/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.poeditor.com'
        );

        $provider->write($translatorBag);
    }

    /**
     * @dataProvider getResponsesForOneLocaleAndOneDomain
     */
    public function testReadForOneLocaleAndOneDomain(string $locale, string $domain, string $responseContent, TranslatorBag $expectedTranslatorBag)
    {
        $loader = $this->getLoader();
        $loader->expects($this->once())
            ->method('load')
            ->willReturn((new XliffFileLoader())->load($responseContent, $locale, $domain));

        $responses = [
            new MockResponse(json_encode([
                'response' => [
                    'status' => 'success',
                    'code' => '200',
                    'message' => 'OK',
                ],
                'result' => [
                    'url' => 'https://api.poeditor.com/v2/download/file/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                ],
            ])),
            new MockResponse($responseContent),
        ];

        $provider = $this->createProvider(
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

    /**
     * @dataProvider getResponsesForManyLocalesAndManyDomains
     */
    public function testReadForManyLocalesAndManyDomains(array $locales, array $domains, array $responseContents, TranslatorBag $expectedTranslatorBag)
    {
        $exportResponses = $downloadResponses = [];
        $consecutiveLoadArguments = [];
        $consecutiveLoadReturns = [];
        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $exportResponses[] = new MockResponse(json_encode([
                    'response' => [
                        'status' => 'success',
                        'code' => '200',
                        'message' => 'OK',
                    ],
                    'result' => [
                        'url' => 'https://api.poeditor.com/v2/download/file/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                    ],
                ]));
                $downloadResponses[] = new MockResponse($responseContents[$locale][$domain]);
                $consecutiveLoadArguments[] = [$responseContents[$locale][$domain], $locale, $domain];
                $consecutiveLoadReturns[] = (new XliffFileLoader())->load($responseContents[$locale][$domain], $locale, $domain);
            }
        }

        $loader = $this->getLoader();
        $loader->expects($this->exactly(\count($consecutiveLoadArguments)))
            ->method('load')
            ->withConsecutive(...$consecutiveLoadArguments)
            ->willReturnOnConsecutiveCalls(...$consecutiveLoadReturns);

        $provider = $this->createProvider(
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
        $successResponse = new MockResponse(json_encode([
            'response' => [
                'status' => 'success',
                'code' => '200',
                'message' => 'OK',
            ],
        ]));

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# comment} other {# comments}}'],
        ]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', [
            'messages' => ['a' => 'trans_fr_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# commentaire} other {# commentaires}}'],
        ]));

        $provider = $this->createProvider(
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

    public function getResponsesForOneLocaleAndOneDomain(): \Generator
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
      <trans-unit id="index.hello" datatype="html">
        <source>index.hello</source>
        <target>Hello</target>
        <note priority="1" from="meaning">index.hello</note>
        <context-group purpose="location">
          <context context-type="sourcefile">index.hello</context>
          <context context-type="linenumber"/>
        </context-group>
      </trans-unit>
      <trans-unit id="index.greetings" datatype="html">
        <source>index.greetings</source>
        <target>Welcome, {firstname}!</target>
        <note priority="1" from="meaning">index.greetings</note>
        <context-group purpose="location">
          <context context-type="sourcefile">index.greetings</context>
          <context context-type="linenumber"/>
        </context-group>
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
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
  <file source-language="en" target-language="fr" datatype="plaintext" original="ng2.template">
    <body>
      <trans-unit id="index.hello" datatype="html">
        <source>index.hello</source>
        <target>Bonjour</target>
        <note priority="1" from="meaning">index.hello</note>
        <context-group purpose="location">
          <context context-type="sourcefile">index.hello</context>
          <context context-type="linenumber"/>
        </context-group>
      </trans-unit>
      <trans-unit id="index.greetings" datatype="html">
        <source>index.greetings</source>
        <target>Bienvenue, {firstname} !</target>
        <note priority="1" from="meaning">index.greetings</note>
        <context-group purpose="location">
          <context context-type="sourcefile">index.greetings</context>
          <context context-type="linenumber"/>
        </context-group>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
            ,
            $expectedTranslatorBagFr,
        ];
    }

    public function getResponsesForManyLocalesAndManyDomains(): \Generator
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
      <trans-unit id="index.hello" datatype="html">
        <source>index.hello</source>
        <target>Hello</target>
        <note priority="1" from="meaning">index.hello</note>
        <context-group purpose="location">
          <context context-type="sourcefile">index.hello</context>
          <context context-type="linenumber"/>
        </context-group>
      </trans-unit>
      <trans-unit id="index.greetings" datatype="html">
        <source>index.greetings</source>
        <target>Welcome, {firstname}!</target>
        <note priority="1" from="meaning">index.greetings</note>
        <context-group purpose="location">
          <context context-type="sourcefile">index.greetings</context>
          <context context-type="linenumber"/>
        </context-group>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
                    ,
                    'validators' => <<<'XLIFF'
<?xml version="1.0" encoding="UTF-8"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
  <file source-language="en" target-language="en" datatype="plaintext" original="ng2.template">
    <body>
      <trans-unit id="firstname.error" datatype="html">
        <source>firstname.error</source>
        <target>Firstname must contains only letters.</target>
        <note priority="1" from="meaning">firstname.error</note>
        <context-group purpose="location">
          <context context-type="sourcefile">firstname.error</context>
          <context context-type="linenumber"/>
        </context-group>
      </trans-unit>
      <trans-unit id="lastname.error" datatype="html">
        <source>lastname.error</source>
        <target>Lastname must contains only letters.</target>
        <note priority="1" from="meaning">lastname.error</note>
        <context-group purpose="location">
          <context context-type="sourcefile">lastname.error</context>
          <context context-type="linenumber"/>
        </context-group>
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
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
  <file source-language="en" target-language="fr" datatype="plaintext" original="ng2.template">
    <body>
      <trans-unit id="index.hello" datatype="html">
        <source>index.hello</source>
        <target>Bonjour</target>
        <note priority="1" from="meaning">index.hello</note>
        <context-group purpose="location">
          <context context-type="sourcefile">index.hello</context>
          <context context-type="linenumber"/>
        </context-group>
      </trans-unit>
      <trans-unit id="index.greetings" datatype="html">
        <source>index.greetings</source>
        <target>Bienvenue, {firstname} !</target>
        <note priority="1" from="meaning">index.greetings</note>
        <context-group purpose="location">
          <context context-type="sourcefile">index.greetings</context>
          <context context-type="linenumber"/>
        </context-group>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
                    ,
                    'validators' => <<<'XLIFF'
<?xml version="1.0" encoding="UTF-8"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
  <file source-language="en" target-language="fr" datatype="plaintext" original="ng2.template">
    <body>
      <trans-unit id="firstname.error" datatype="html">
        <source>firstname.error</source>
        <target>Le prénom ne peut contenir que des lettres.</target>
        <note priority="1" from="meaning">firstname.error</note>
        <context-group purpose="location">
          <context context-type="sourcefile">firstname.error</context>
          <context context-type="linenumber"/>
        </context-group>
      </trans-unit>
      <trans-unit id="lastname.error" datatype="html">
        <source>lastname.error</source>
        <target>Le nom de famille ne peut contenir que des lettres.</target>
        <note priority="1" from="meaning">lastname.error</note>
        <context-group purpose="location">
          <context context-type="sourcefile">lastname.error</context>
          <context context-type="linenumber"/>
        </context-group>
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
