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
use Symfony\Component\Translation\Exception\ProviderException;
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

    public function toStringProvider(): iterable
    {
        yield [
            $this->createProvider($this->getClient()->withOptions([
                'base_uri' => 'https://localise.biz/api/',
                'headers' => [
                    'Authorization' => 'Loco API_KEY',
                ],
            ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/'),
            'loco://localise.biz/api/',
        ];

        yield [
            $this->createProvider($this->getClient()->withOptions([
                'base_uri' => 'https://example.com',
                'headers' => [
                    'Authorization' => 'Loco API_KEY',
                ],
            ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'example.com'),
            'loco://example.com',
        ];

        yield [
            $this->createProvider($this->getClient()->withOptions([
                'base_uri' => 'https://example.com:99',
                'headers' => [
                    'Authorization' => 'Loco API_KEY',
                ],
            ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'example.com:99'),
            'loco://example.com:99',
        ];
    }

    public function testCompleteWriteProcess()
    {
        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'createAsset1' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'id' => 'messages__a',
                    'text' => 'a',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                self::assertSame('POST', $method);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame($expectedBody, $options['body']);

                return new MockResponse('{"id": "messages__a"}', ['http_code' => 201]);
            },
            'getTags1' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[]');
            },
            'createTag1' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame(http_build_query(['name' => 'messages']), $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
            'tagAsset1' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags/messages.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('messages__a', $options['body']);

                return new MockResponse();
            },
            'createAsset2' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'id' => 'validators__post.num_comments',
                    'text' => 'post.num_comments',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                self::assertSame('POST', $method);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame($expectedBody, $options['body']);

                return new MockResponse('{"id": "validators__post.num_comments"}', ['http_code' => 201]);
            },
            'getTags2' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('["messages"]');
            },
            'createTag2' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame(http_build_query(['name' => 'validators']), $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
            'tagAsset2' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags/validators.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('validators__post.num_comments', $options['body']);

                return new MockResponse();
            },
            'getLocales1' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/locales', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"code":"en"}]');
            },
            'getAssetsIds1' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/assets?filter=messages', $url);
                self::assertSame(['filter' => 'messages'], $options['query']);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"id":"messages__foo.existing_key"},{"id":"messages__a"}]');
            },
            'translateAsset1' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/translations/messages__a/en', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('trans_en_a', $options['body']);

                return new MockResponse();
            },
            'getAssetsIds2' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/assets?filter=validators', $url);
                self::assertSame(['filter' => 'validators'], $options['query']);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"id":"validators__foo.existing_key"},{"id":"validators__post.num_comments"}]');
            },
            'translateAsset2' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/translations/validators__post.num_comments/en', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('{count, plural, one {# comment} other {# comments}}', $options['body']);

                return new MockResponse();
            },
            'getLocales2' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/locales', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"code":"en"}]');
            },
            'createLocale1' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/locales', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('code=fr', $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
            'getAssetsIds3' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/assets?filter=messages', $url);
                self::assertSame(['filter' => 'messages'], $options['query']);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"id":"messages__a"}]');
            },
            'translateAsset3' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/translations/messages__a/fr', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('trans_fr_a', $options['body']);

                return new MockResponse();
            },
            'getAssetsIds4' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/assets?filter=validators', $url);
                self::assertSame(['filter' => 'validators'], $options['query']);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"id":"validators__post.num_comments"}]');
            },
            'translateAsset4' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/translations/validators__post.num_comments/fr', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('{count, plural, one {# commentaire} other {# commentaires}}', $options['body']);

                return new MockResponse();
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
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        $provider->write($translatorBag);
    }

    public function testWriteCreateAssetServerError()
    {
        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'createAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'id' => 'messages__a',
                    'text' => 'a',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                self::assertSame('POST', $method);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame($expectedBody, $options['body']);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));

        $provider = $this->createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        self::expectException(ProviderException::class);
        self::expectExceptionMessage('Unable to add new translation key "a" to Loco: (status code: "500").');

        $provider->write($translatorBag);
    }

    public function testWriteCreateTagServerError()
    {
        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'createAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'id' => 'messages__a',
                    'text' => 'a',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                self::assertSame('POST', $method);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame($expectedBody, $options['body']);

                return new MockResponse('{"id": "messages__a"}', ['http_code' => 201]);
            },
            'getTags' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[]');
            },
            'createTag' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame(http_build_query(['name' => 'messages']), $options['body']);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));

        $provider = $this->createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        self::expectException(ProviderException::class);
        self::expectExceptionMessage('Unable to create tag "messages" on Loco.');

        $provider->write($translatorBag);
    }

    public function testWriteTagAssetsServerError()
    {
        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'createAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'id' => 'messages__a',
                    'text' => 'a',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                self::assertSame('POST', $method);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame($expectedBody, $options['body']);

                return new MockResponse('{"id": "messages__a"}', ['http_code' => 201]);
            },
            'getTags' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[]');
            },
            'createTag' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame(http_build_query(['name' => 'messages']), $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
            'tagAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags/messages.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('messages__a', $options['body']);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));

        $provider = $this->createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        self::expectException(ProviderException::class);
        self::expectExceptionMessage('Unable to tag assets with "messages" on Loco.');

        $provider->write($translatorBag);
    }

    public function testWriteTagAssetsServerErrorWithComma()
    {
        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'createAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'id' => 'messages__a',
                    'text' => 'a',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                self::assertSame('POST', $method);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame($expectedBody, $options['body']);

                return new MockResponse('{"id": "messages__a,messages__b"}', ['http_code' => 201]);
            },
            'getTags' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[]');
            },
            'createTag' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame(http_build_query(['name' => 'messages']), $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
            'tagAssetWithComma' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/assets/messages__a%2Cmessages__b/tags', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('name=messages', $options['body']);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));

        $provider = $this->createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        self::expectException(ProviderException::class);
        self::expectExceptionMessage('Unable to tag asset "messages__a,messages__b" with "messages" on Loco.');

        $provider->write($translatorBag);
    }

    public function testWriteCreateLocaleServerError()
    {
        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'createAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'id' => 'messages__a',
                    'text' => 'a',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                self::assertSame('POST', $method);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame($expectedBody, $options['body']);

                return new MockResponse('{"id": "messages__a"}', ['http_code' => 201]);
            },
            'getTags' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[]');
            },
            'createTag' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame(http_build_query(['name' => 'messages']), $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
            'tagAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags/messages.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('messages__a', $options['body']);

                return new MockResponse();
            },
            'getLocales' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/locales', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"code":"fr"}]');
            },
            'createLocale' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/locales', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));

        $provider = $this->createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        self::expectException(ProviderException::class);
        self::expectExceptionMessage('Unable to create locale "en" on Loco.');

        $provider->write($translatorBag);
    }

    public function testWriteGetAssetsIdsServerError()
    {
        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'createAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'id' => 'messages__a',
                    'text' => 'a',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                self::assertSame('POST', $method);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame($expectedBody, $options['body']);

                return new MockResponse('{"id": "messages__a"}', ['http_code' => 201]);
            },
            'getTags' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[]');
            },
            'createTag' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame(http_build_query(['name' => 'messages']), $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
            'tagAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags/messages.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('messages__a', $options['body']);

                return new MockResponse();
            },
            'getLocales' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/locales', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"code":"en"}]');
            },
            'getAssetsIds' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/assets?filter=messages', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', [
            'messages' => ['a' => 'trans_fr_a'],
        ]));

        $provider = $this->createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        self::expectException(ProviderException::class);
        self::expectExceptionMessage('Unable to get assets from Loco.');

        $provider->write($translatorBag);
    }

    public function testWriteTranslateAssetsServerError()
    {
        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'createAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'id' => 'messages__a',
                    'text' => 'a',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                self::assertSame('POST', $method);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame($expectedBody, $options['body']);

                return new MockResponse('{"id": "messages__a"}', ['http_code' => 201]);
            },
            'getTags' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[]');
            },
            'createTag' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame(http_build_query(['name' => 'messages']), $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
            'tagAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/tags/messages.json', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('messages__a', $options['body']);

                return new MockResponse();
            },
            'getLocales' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/locales', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"code":"en"}]');
            },
            'getAssetsIds' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('GET', $method);
                self::assertSame('https://localise.biz/api/assets?filter=messages', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return new MockResponse('[{"id":"messages__foo.existing_key"},{"id":"messages__a"}]');
            },
            'translateAsset' => function (string $method, string $url, array $options = []) use ($expectedAuthHeader): ResponseInterface {
                self::assertSame('POST', $method);
                self::assertSame('https://localise.biz/api/translations/messages__a/en', $url);
                self::assertSame($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                self::assertSame('trans_en_a', $options['body']);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', [
            'messages' => ['a' => 'trans_fr_a'],
        ]));

        $provider = $this->createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => ['Authorization' => 'Loco API_KEY'],
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');

        self::expectException(ProviderException::class);
        self::expectExceptionMessage('Unable to add translation for key "messages__a" in locale "en" to Loco.');

        $provider->write($translatorBag);
    }

    /**
     * @dataProvider getResponsesForOneLocaleAndOneDomain
     */
    public function testReadForOneLocaleAndOneDomain(string $locale, string $domain, string $responseContent, TranslatorBag $expectedTranslatorBag)
    {
        $loader = $this->getLoader();
        $loader->expects(self::once())
            ->method('load')
            ->willReturn((new XliffFileLoader())->load($responseContent, $locale, $domain));

        $provider = $this->createProvider((new MockHttpClient(new MockResponse($responseContent)))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => [
                'Authorization' => 'Loco API_KEY',
            ],
        ]), $loader, $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');
        $translatorBag = $provider->read([$domain], [$locale]);
        // We don't want to assert equality of metadata here, due to the ArrayLoader usage.
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $catalogue->deleteMetadata('', '');
        }

        self::assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
    }

    /**
     * @dataProvider getResponsesForManyLocalesAndManyDomains
     */
    public function testReadForManyLocalesAndManyDomains(array $locales, array $domains, array $responseContents, TranslatorBag $expectedTranslatorBag)
    {
        $responses = [];
        $consecutiveLoadArguments = [];
        $consecutiveLoadReturns = [];

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $responses[] = new MockResponse($responseContents[$locale][$domain]);
                $consecutiveLoadArguments[] = [$responseContents[$locale][$domain], $locale, $domain];
                $consecutiveLoadReturns[] = (new XliffFileLoader())->load($responseContents[$locale][$domain], $locale, $domain);
            }
        }

        $loader = $this->getLoader();
        $loader->expects(self::exactly(\count($consecutiveLoadArguments)))
            ->method('load')
            ->withConsecutive(...$consecutiveLoadArguments)
            ->willReturnOnConsecutiveCalls(...$consecutiveLoadReturns);

        $provider = $this->createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://localise.biz/api/',
            'headers' => [
                'Authorization' => 'Loco API_KEY',
            ],
        ]), $loader, $this->getLogger(), $this->getDefaultLocale(), 'localise.biz/api/');
        $translatorBag = $provider->read($domains, $locales);
        // We don't want to assert equality of metadata here, due to the ArrayLoader usage.
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $catalogue->deleteMetadata('', '');
        }

        self::assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
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

        $provider = $this->createProvider(
            new MockHttpClient([
                function (string $method, string $url, array $options = []): ResponseInterface {
                    self::assertSame('GET', $method);
                    self::assertSame('https://localise.biz/api/assets?filter=messages', $url);
                    self::assertSame(['filter' => 'messages'], $options['query']);

                    return new MockResponse('[{"id":"messages__a"}]');
                },
                function (string $method, string $url): MockResponse {
                    self::assertSame('DELETE', $method);
                    self::assertSame('https://localise.biz/api/assets/messages__a.json', $url);

                    return new MockResponse();
                },
                function (string $method, string $url, array $options = []): ResponseInterface {
                    self::assertSame('GET', $method);
                    self::assertSame('https://localise.biz/api/assets?filter=validators', $url);
                    self::assertSame(['filter' => 'validators'], $options['query']);

                    return new MockResponse('[{"id":"validators__post.num_comments"}]');
                },
                function (string $method, string $url): MockResponse {
                    self::assertSame('DELETE', $method);
                    self::assertSame('https://localise.biz/api/assets/validators__post.num_comments.json', $url);

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

        $provider = $this->createProvider(
            new MockHttpClient([
                function (string $method, string $url, array $options = []): ResponseInterface {
                    self::assertSame('GET', $method);
                    self::assertSame('https://localise.biz/api/assets?filter=messages', $url);
                    self::assertSame(['filter' => 'messages'], $options['query']);

                    return new MockResponse('[{"id":"messages__a"}]');
                },
                function (string $method, string $url): MockResponse {
                    self::assertSame('DELETE', $method);
                    self::assertSame('https://localise.biz/api/assets/messages__a.json', $url);

                    return new MockResponse('', ['http_code' => 500]);
                },
            ], 'https://localise.biz/api/'),
            $this->getLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'localise.biz/api/'
        );

        self::expectException(ProviderException::class);
        self::expectExceptionMessage('Unable to delete translation key "messages__a" to Loco.');

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
        ], 'fr'));

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

    public function getResponsesForManyLocalesAndManyDomains(): \Generator
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
XLIFF
                    ,
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
XLIFF
                    ,
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
XLIFF
                    ,
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
XLIFF
                    ,
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
XLIFF
                    ,
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
XLIFF
                    ,
                ],
            ],
            $expectedTranslatorBag,
        ];
    }
}
