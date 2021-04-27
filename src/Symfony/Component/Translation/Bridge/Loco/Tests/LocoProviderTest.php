<?php

namespace Symfony\Component\Translation\Bridge\Loco\Tests;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Translation\Bridge\Loco\LocoProvider;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Test\ProviderTestCase;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LocoProviderTest extends ProviderTestCase
{
    public function createProvider(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint): ProviderInterface
    {
        return new LocoProvider($client, $loader, $logger, $defaultLocale, $endpoint);
    }

    public function testCompleteWriteProcess()
    {
        $createAssetResponse = $this->createMock(ResponseInterface::class);
        $createAssetResponse->expects($this->exactly(4))
            ->method('getStatusCode')
            ->willReturn(201);

        $getLocalesResponse = $this->createMock(ResponseInterface::class);
        $getLocalesResponse->expects($this->exactly(4))
            ->method('getStatusCode')
            ->willReturn(200);
        $getLocalesResponse->expects($this->exactly(2))
            ->method('getContent')
            ->with(false)
            ->willReturn('[{"code":"en"}]');

        $createLocaleResponse = $this->createMock(ResponseInterface::class);
        $createLocaleResponse->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(201);

        $translateAssetResponse = $this->createMock(ResponseInterface::class);
        $translateAssetResponse->expects($this->exactly(8))
            ->method('getStatusCode')
            ->willReturn(200);

        $getTagsEmptyResponse = $this->createMock(ResponseInterface::class);
        $getTagsEmptyResponse->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $getTagsEmptyResponse->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('[]');

        $getTagsNotEmptyResponse = $this->createMock(ResponseInterface::class);
        $getTagsNotEmptyResponse->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $getTagsNotEmptyResponse->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('["messages"]');

        $createTagResponse = $this->createMock(ResponseInterface::class);
        $createTagResponse->expects($this->exactly(4))
            ->method('getStatusCode')
            ->willReturn(201);

        $tagAssetResponse = $this->createMock(ResponseInterface::class);
        $tagAssetResponse->expects($this->exactly(4))
            ->method('getStatusCode')
            ->willReturn(200);

        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'createAsset1' => function (string $method, string $url, array $options = []) use ($createAssetResponse, $expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'name' => 'a',
                    'id' => 'a',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                $this->assertEquals('POST', $method);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals($expectedBody, $options['body']);

                return $createAssetResponse;
            },
            'getTags1' => function (string $method, string $url, array $options = []) use ($getTagsEmptyResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('GET', $method);
                $this->assertEquals('https://localise.biz/api/tags.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return $getTagsEmptyResponse;
            },
            'createTag1' => function (string $method, string $url, array $options = []) use ($createTagResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/tags.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals(http_build_query(['name' => 'messages']), $options['body']);

                return $createTagResponse;
            },
            'tagAsset1' => function (string $method, string $url, array $options = []) use ($tagAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/tags/messages.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('a', $options['body']);

                return $tagAssetResponse;
            },
            'createAsset2' => function (string $method, string $url, array $options = []) use ($createAssetResponse, $expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'name' => 'post.num_comments',
                    'id' => 'post.num_comments',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                $this->assertEquals('POST', $method);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals($expectedBody, $options['body']);

                return $createAssetResponse;
            },
            'getTags2' => function (string $method, string $url, array $options = []) use ($getTagsNotEmptyResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('GET', $method);
                $this->assertEquals('https://localise.biz/api/tags.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return $getTagsNotEmptyResponse;
            },
            'createTag2' => function (string $method, string $url, array $options = []) use ($createTagResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/tags.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals(http_build_query(['name' => 'validators']), $options['body']);

                return $createTagResponse;
            },
            'tagAsset2' => function (string $method, string $url, array $options = []) use ($tagAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/tags/validators.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('post.num_comments', $options['body']);

                return $tagAssetResponse;
            },

            'getLocales1' => function (string $method, string $url, array $options = []) use ($getLocalesResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('GET', $method);
                $this->assertEquals('https://localise.biz/api/locales', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return $getLocalesResponse;
            },

            'translateAsset1' => function (string $method, string $url, array $options = []) use ($translateAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/translations/a/en', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('trans_en_a', $options['body']);

                return $translateAssetResponse;
            },
            'translateAsset2' => function (string $method, string $url, array $options = []) use ($translateAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/translations/post.num_comments/en', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('{count, plural, one {# comment} other {# comments}}', $options['body']);

                return $translateAssetResponse;
            },

            'getLocales2' => function (string $method, string $url, array $options = []) use ($getLocalesResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('GET', $method);
                $this->assertEquals('https://localise.biz/api/locales', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return $getLocalesResponse;
            },

            'createLocale1' => function (string $method, string $url, array $options = []) use ($createLocaleResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/locales', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('code=fr', $options['body']);

                return $createLocaleResponse;
            },

            'translateAsset3' => function (string $method, string $url, array $options = []) use ($translateAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/translations/a/fr', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('trans_fr_a', $options['body']);

                return $translateAssetResponse;
            },
            'translateAsset4' => function (string $method, string $url, array $options = []) use ($translateAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/translations/post.num_comments/fr', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('{count, plural, one {# commentaire} other {# commentaires}}', $options['body']);

                return $translateAssetResponse;
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

        $provider = $this->createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => [
                'Authorization' => 'Loco API_KEY',
            ],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');
        $provider->write($translatorBag);
    }

    /**
     * @dataProvider getLocoResponsesForOneLocaleAndOneDomain
     */
    public function testReadForOneLocaleAndOneDomain(string $locale, string $domain, string $responseContent, TranslatorBag $expectedTranslatorBag)
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($responseContent);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $loader = $this->getLoader();
        $loader->expects($this->once())
            ->method('load')
            ->willReturn($expectedTranslatorBag->getCatalogue($locale));

        $locoProvider = $this->createProvider((new MockHttpClient($response))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => [
                'Authorization' => 'Loco API_KEY',
            ],
        ]), $loader, $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');
        $translatorBag = $locoProvider->read([$domain], [$locale]);

        $this->assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
    }

    /**
     * @dataProvider getLocoResponsesForManyLocalesAndManyDomains
     */
    public function testReadForManyLocalesAndManyDomains(array $locales, array $domains, array $responseContents, array $expectedTranslatorBags)
    {
        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $response = $this->createMock(ResponseInterface::class);
                $response->expects($this->once())
                    ->method('getContent')
                    ->willReturn($responseContents[$domain][$locale]);
                $response->expects($this->exactly(2))
                    ->method('getStatusCode')
                    ->willReturn(200);

                $locoProvider = new LocoProvider((new MockHttpClient($response))->withOptions([
                    'base_uri' => 'https://localise.biz/api/',
                    'headers' => [
                        'Authorization' => 'Loco API_KEY',
                    ],
                ]), new XliffFileLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');
                $translatorBag = $locoProvider->read([$domain], [$locale]);
                // We don't want to assert equality of metadata here, due to the ArrayLoader usage.
                $translatorBag->getCatalogue($locale)->deleteMetadata('foo', '');

                $this->assertEquals($expectedTranslatorBags[$domain]->getCatalogue($locale), $translatorBag->getCatalogue($locale));
            }
        }
    }

    public function toStringProvider(): iterable
    {
        yield [
            new LocoProvider($this->getClient()->withOptions([
                'base_uri' => 'https://localise.biz/api/',
                'headers' => [
                    'Authorization' => 'Loco API_KEY',
                ],
            ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/'),
            'loco://localise.biz/api/',
        ];

        yield [
            new LocoProvider($this->getClient()->withOptions([
                'base_uri' => 'https://example.com',
                'headers' => [
                    'Authorization' => 'Loco API_KEY',
                ],
            ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'example.com'),
            'loco://example.com',
        ];

        yield [
            new LocoProvider($this->getClient()->withOptions([
                'base_uri' => 'https://example.com:99',
                'headers' => [
                    'Authorization' => 'Loco API_KEY',
                ],
            ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'example.com:99'),
            'loco://example.com:99',
        ];
    }

    public function getLocoResponsesForOneLocaleAndOneDomain(): \Generator
    {
        $arrayLoader = new ArrayLoader();

        $expectedTranslatorBagEn = new TranslatorBag();
        $expectedTranslatorBagEn->addCatalogue($arrayLoader->load([
            'index.hello' => 'Hello',
            'index.greetings' => 'Welcome, {firstname}!',
        ], 'en', 'messages'));

        yield ['en', 'messages', <<<'XLIFF'
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
      <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
        <source>index.greetings</source>
        <target state="translated">Welcome, {firstname}!</target>
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
        ], 'fr', 'messages'));

        yield ['fr', 'messages', <<<'XLIFF'
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
      <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
        <source>index.greetings</source>
        <target state="translated">Bienvenue, {firstname} !</target>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
            ,
            $expectedTranslatorBagFr,
        ];
    }

    public function getLocoResponsesForManyLocalesAndManyDomains(): \Generator
    {
        $arrayLoader = new ArrayLoader();

        $expectedTranslatorBagMessages = new TranslatorBag();
        $expectedTranslatorBagMessages->addCatalogue($arrayLoader->load([
            'index.hello' => 'Hello',
            'index.greetings' => 'Welcome, {firstname}!',
        ], 'en', 'messages'));
        $expectedTranslatorBagMessages->addCatalogue($arrayLoader->load([
            'index.hello' => 'Bonjour',
            'index.greetings' => 'Bienvenue, {firstname} !',
        ], 'fr', 'messages'));

        $expectedTranslatorBagValidators = new TranslatorBag();
        $expectedTranslatorBagValidators->addCatalogue($arrayLoader->load([
            'firstname.error' => 'Firstname must contains only letters.',
            'lastname.error' => 'Lastname must contains only letters.',
        ], 'en', 'validators'));
        $expectedTranslatorBagValidators->addCatalogue($arrayLoader->load([
            'firstname.error' => 'Le prénom ne peut contenir que des lettres.',
            'lastname.error' => 'Le nom de famille ne peut contenir que des lettres.',
        ], 'fr', 'validators'));

        yield [
            ['en', 'fr'],
            ['messages', 'validators'],
            [
                'messages' => [
                    'en' => <<<'XLIFF'
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
      <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
        <source>index.greetings</source>
        <target state="translated">Welcome, {firstname}!</target>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
                    ,
                    'fr' => <<<'XLIFF'
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
      <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
        <source>index.greetings</source>
        <target state="translated">Bienvenue, {firstname} !</target>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF
                    ,
                ],
                'validators' => [
                    'en' => <<<'XLIFF'
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
XLIFF
                    ,
                    'fr' => <<<'XLIFF'
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
XLIFF
                    ,
            ],
            ],
            [
                'messages' => $expectedTranslatorBagMessages,
                'validators' => $expectedTranslatorBagValidators,
            ],
        ];
    }
}
