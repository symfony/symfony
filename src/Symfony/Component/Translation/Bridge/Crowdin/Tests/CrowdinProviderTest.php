<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Crowdin\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\Bridge\Crowdin\CrowdinProvider;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
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

class CrowdinProviderTest extends ProviderTestCase
{
    protected function getLoader(): LoaderInterface
    {
        return $this->loader ??= new XliffFileLoader();
    }

    public static function createProvider(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint, ?string $projectId = null): ProviderInterface
    {
        return new CrowdinProvider($client, $loader, $logger, new XliffFileDumper(), $defaultLocale, $endpoint, $projectId);
    }

    public static function toStringProvider(): iterable
    {
        yield [
            self::createProvider((new MockHttpClient())->withOptions([
                'base_uri' => 'https://api.crowdin.com/api/v2/',
                'auth_bearer' => 'API_TOKEN',
            ]), new ArrayLoader(), new NullLogger(), 'en', 'api.crowdin.com', '1'),
            'crowdin://api.crowdin.com',
        ];

        yield [
            self::createProvider((new MockHttpClient())->withOptions([
                'base_uri' => 'https://domain.api.crowdin.com/api/v2/',
                'auth_bearer' => 'API_TOKEN',
            ]), new ArrayLoader(), new NullLogger(), 'en', 'domain.api.crowdin.com', '1'),
            'crowdin://domain.api.crowdin.com',
        ];

        yield [
            self::createProvider((new MockHttpClient())->withOptions([
                'base_uri' => 'https://api.crowdin.com:99/api/v2/',
                'auth_bearer' => 'API_TOKEN',
            ]), new ArrayLoader(), new NullLogger(), 'en', 'api.crowdin.com:99', '1'),
            'crowdin://api.crowdin.com:99',
        ];
    }

    public function testCompleteWriteProcessAddFiles()
    {
        $this->xliffFileDumper = new XliffFileDumper();

        $expectedMessagesFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $expectedValidatorsFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="post.num_comments">
                    <source>post.num_comments</source>
                    <target>{count, plural, one {# comment} other {# comments}}</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);
                $this->assertSame('Authorization: Bearer API_TOKEN', $options['normalized_headers']['authorization'][0]);

                return new JsonMockResponse(['data' => []]);
            },
            'getProject' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => [],
                    'languageMapping' => [],
                ]]);
            },
            'addStorage' => function (string $method, string $url, array $options = []) use ($expectedMessagesFileContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'addFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);
                $this->assertSame('{"storageId":19,"name":"messages.xlf"}', $options['body']);

                return new JsonMockResponse(['data' => ['id' => 199, 'name' => 'messages.xlf']], ['http_code' => 201]);
            },
            'addStorage2' => function (string $method, string $url, array $options = []) use ($expectedValidatorsFileContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: validators.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedValidatorsFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'addFile2' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);
                $this->assertSame('{"storageId":19,"name":"validators.xlf"}', $options['body']);

                return new JsonMockResponse(['data' => ['id' => 200, 'name' => 'validators.xlf']], ['http_code' => 201]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# comment} other {# comments}}'],
        ]));

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $provider->write($translatorBag);
    }

    public function testWriteAddFileServerError()
    {
        $this->xliffFileDumper = new XliffFileDumper();

        $expectedMessagesFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);
                $this->assertSame('Authorization: Bearer API_TOKEN', $options['normalized_headers']['authorization'][0]);

                return new JsonMockResponse(['data' => []]);
            },
            'getProject' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => [],
                    'languageMapping' => [],
                ]]);
            },
            'addStorage' => function (string $method, string $url, array $options = []) use ($expectedMessagesFileContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'addFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);
                $this->assertSame('{"storageId":19,"name":"messages.xlf"}', $options['body']);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# comment} other {# comments}}'],
        ]));

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to create a File in Crowdin for domain "messages".');

        $provider->write($translatorBag);
    }

    public function testWriteUpdateFileServerError()
    {
        $this->xliffFileDumper = new XliffFileDumper();

        $sourceFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $expectedMessagesFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                  <trans-unit id="%s" resname="b">
                    <source>b</source>
                    <target>trans_en_b</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);
                $this->assertSame('Authorization: Bearer API_TOKEN', $options['normalized_headers']['authorization'][0]);

                return new JsonMockResponse([
                    'data' => [
                        ['data' => [
                            'id' => 12,
                            'name' => 'messages.xlf',
                        ]],
                    ],
                ]);
            },
            'getProject' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => [],
                    'languageMapping' => [],
                ]]);
            },
            'downloadSource' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12/download', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url) use ($sourceFileContent): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse($sourceFileContent);
            },
            'addStorage' => function (string $method, string $url, array $options = []) use ($expectedMessagesFileContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'updateFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('PUT', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12', $url);
                $this->assertSame('{"storageId":19}', $options['body']);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['b' => 'trans_en_b'],
            'validators' => ['post.num_comments' => '{count, plural, one {# comment} other {# comments}}'],
        ]));

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to update file in Crowdin for file ID "12" and domain "messages".');

        $provider->write($translatorBag);
    }

    public function testWriteUploadTranslationsServerError()
    {
        $this->xliffFileDumper = new XliffFileDumper();

        $sourceFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $expectedMessagesTranslationsContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="fr" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_fr_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $expectedMessagesFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        ['data' => [
                            'id' => 12,
                            'name' => 'messages.xlf',
                        ]],
                    ],
                ]);
            },
            'getProject' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => ['fr'],
                    'languageMapping' => [],
                ]]);
            },
            'downloadSource' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12/download', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url) use ($sourceFileContent): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse($sourceFileContent);
            },
            'addStorage' => function (string $method, string $url, array $options = []) use ($expectedMessagesFileContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'updateFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('PUT', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12', $url);
                $this->assertSame('{"storageId":19}', $options['body']);

                return new JsonMockResponse(['data' => ['id' => 12, 'name' => 'messages.xlf']]);
            },
            'addStorage2' => function (string $method, string $url, array $options = []) use ($expectedMessagesTranslationsContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesTranslationsContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'importTranslations' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/imports', $url);
                $this->assertSame(json_encode(['storageId' => 19, 'languageIds' => ['fr'], 'fileId' => 12]), $options['body']);

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

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to upload translations to Crowdin.');

        $provider->write($translatorBag);
    }

    public function testWriteUploadTranslationsWithFail()
    {
        $sourceFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $expectedMessagesTranslationsContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="fr" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_fr_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $expectedMessagesFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        [
                            'data' => [
                                'id' => 12,
                                'name' => 'messages.xlf',
                            ],
                        ],
                    ],
                ]);
            },
            'getProject' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => ['fr'],
                    'languageMapping' => [],
                ]]);
            },
            'downloadSource' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12/download', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url) use ($sourceFileContent): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse($sourceFileContent);
            },
            'addStorage' => function (string $method, string $url, array $options = []) use ($expectedMessagesFileContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'updateFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('PUT', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12', $url);
                $this->assertSame(json_encode(['storageId' => 19]), $options['body']);

                return new JsonMockResponse(['data' => ['id' => 12, 'name' => 'messages.xlf']]);
            },
            'addTranslationStorage' => function (string $method, string $url, array $options = []) use ($expectedMessagesTranslationsContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesTranslationsContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'importTranslations' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/imports', $url);
                $this->assertSame(json_encode(['storageId' => 19, 'languageIds' => ['fr'], 'fileId' => 12]), $options['body']);

                return new JsonMockResponse(['data' => ['identifier' => '4d3adb0f-cea4-42a4-bb20-536c181da02c']], ['http_code' => 202]);
            },
            'checkImportTranslationsWithCreated' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/imports/4d3adb0f-cea4-42a4-bb20-536c181da02c', $url);

                return new JsonMockResponse(['data' => ['status' => 'created']], ['http_code' => 200]);
            },
            'checkImportTranslationsWithInProgress' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/imports/4d3adb0f-cea4-42a4-bb20-536c181da02c', $url);

                return new JsonMockResponse(['data' => ['status' => 'in_progress']], ['http_code' => 200]);
            },
            'checkImportTranslationsWithFail' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/imports/4d3adb0f-cea4-42a4-bb20-536c181da02c', $url);

                return new JsonMockResponse(['data' => ['status' => 'failed']], ['http_code' => 200]);
            },
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Unable to upload translations to Crowdin.');

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
        ]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', [
            'messages' => ['a' => 'trans_fr_a'],
        ]));

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $logger, $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $provider->write($translatorBag);
    }

    public function testCompleteWriteProcessUpdateFiles()
    {
        $this->xliffFileDumper = new XliffFileDumper();

        $sourceFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $expectedMessagesFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                  <trans-unit id="%s" resname="b">
                    <source>b</source>
                    <target>trans_en_b</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        ['data' => [
                            'id' => 12,
                            'name' => 'messages.xlf',
                        ]],
                    ],
                ]);
            },
            'getProject' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => [],
                    'languageMapping' => [],
                ]]);
            },
            'downloadSource' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12/download', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url) use ($sourceFileContent): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse($sourceFileContent);
            },
            'addStorage' => function (string $method, string $url, array $options = []) use ($expectedMessagesFileContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'updateFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('PUT', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12', $url);
                $this->assertSame('{"storageId":19}', $options['body']);

                return new JsonMockResponse(['data' => ['id' => 199, 'name' => 'messages.xlf']]);
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a', 'b' => 'trans_en_b'],
        ]));

        $provider = self::createProvider($httpClient = (new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $provider->write($translatorBag);
    }

    #[DataProvider('getResponsesForProcessAddFileAndUploadTranslations')]
    public function testCompleteWriteProcessAddFileAndUploadTranslations(TranslatorBag $translatorBag, string $expectedLocale, string $expectedMessagesTranslationsContent)
    {
        $this->xliffFileDumper = new XliffFileDumper();

        $sourceFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $expectedMessagesFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        ['data' => [
                            'id' => 12,
                            'name' => 'messages.xlf',
                        ]],
                    ],
                ]);
            },
            'getProject' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => ['fr', 'uk', 'en-GB'],
                    'languageMapping' => ['pt-PT' => ['locale' => 'pt'], 'uk' => ['locale' => 'uk-UA']],
                ]]);
            },
            'downloadSource' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12/download', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url) use ($sourceFileContent): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse($sourceFileContent);
            },
            'addStorage' => function (string $method, string $url, array $options = []) use ($expectedMessagesFileContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'updateFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('PUT', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12', $url);
                $this->assertSame('{"storageId":19}', $options['body']);

                return new JsonMockResponse(['data' => ['id' => 12, 'name' => 'messages.xlf']]);
            },
            'addStorage2' => function (string $method, string $url, array $options = []) use ($expectedMessagesTranslationsContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesTranslationsContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'importTranslations' => function (string $method, string $url, array $options = []) use ($expectedLocale): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/imports', $url);
                $this->assertSame(json_encode(['storageId' => 19, 'languageIds' => [$expectedLocale], 'fileId' => 12]), $options['body']);

                return new JsonMockResponse(['data' => ['identifier' => '4d3adb0f-cea4-42a4-bb20-536c181da02a']], ['http_code' => 202]);
            },
            'checkImportTranslations' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/imports/4d3adb0f-cea4-42a4-bb20-536c181da02a', $url);

                return new JsonMockResponse(['data' => ['status' => 'finished']], ['http_code' => 200]);
            },
        ];

        $provider = self::createProvider($httpClient = (new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $provider->write($translatorBag);

        $this->assertSame(\count($responses), $httpClient->getRequestsCount());
    }

    public static function getResponsesForProcessAddFileAndUploadTranslations(): \Generator
    {
        $arrayLoader = new ArrayLoader();

        $translatorBagFr = new TranslatorBag();
        $translatorBagFr->addCatalogue($arrayLoader->load([
            'a' => 'trans_en_a',
        ], 'en'));
        $translatorBagFr->addCatalogue($arrayLoader->load([
            'a' => 'trans_fr_a',
        ], 'fr'));

        yield [$translatorBagFr, 'fr', <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="fr" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_fr_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF
        ];

        $translatorBagPt = new TranslatorBag();
        $translatorBagPt->addCatalogue($arrayLoader->load([
            'a' => 'trans_en_a',
        ], 'en'));
        $translatorBagPt->addCatalogue($arrayLoader->load([
            'a' => 'trans_pt_a',
        ], 'pt'));

        $translatorBagUk = new TranslatorBag();
        $translatorBagUk->addCatalogue($arrayLoader->load([
            'a' => 'trans_en_a',
        ], 'en'));
        $translatorBagUk->addCatalogue($arrayLoader->load([
            'a' => 'trans_uk_a',
        ], 'uk_UA'));

        // the "uk" language is mapped to the "uk-UA" locale, which Symfony spells "uk_UA"
        yield [$translatorBagUk, 'uk', <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="uk-UA" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_uk_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF
        ];

        yield [$translatorBagPt, 'pt-PT', <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="pt" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_pt_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF
        ];

        $translatorBagEnGb = new TranslatorBag();
        $translatorBagEnGb->addCatalogue($arrayLoader->load([
            'a' => 'trans_en_a',
        ], 'en'));
        $translatorBagEnGb->addCatalogue($arrayLoader->load([
            'a' => 'trans_en_gb_a',
        ], 'en_GB'));

        yield [$translatorBagEnGb, 'en-GB', <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en-GB" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_gb_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF
        ];
    }

    public function testFilesAreAddedBeforeTranslations()
    {
        $arrayLoader = new ArrayLoader();

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue($arrayLoader->load([
            'a' => 'trans_fr_a',
        ], 'fr'));
        $translatorBag->addCatalogue($arrayLoader->load([
            'a' => 'trans_en_a',
        ], 'en'));

        $expectedMessagesFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>
            XLIFF;

        $expectedMessagesTranslationsContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="fr" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_fr_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>
            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse(['data' => []]);
            },
            'getProject' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => ['fr'],
                    'languageMapping' => [],
                ]]);
            },
            'addStorage' => function (string $method, string $url, array $options = []) use ($expectedMessagesFileContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'addFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);
                $this->assertSame('{"storageId":19,"name":"messages.xlf"}', $options['body']);

                return new JsonMockResponse(['data' => ['id' => 199, 'name' => 'messages.xlf']], ['http_code' => 201]);
            },
            'addStorage2' => function (string $method, string $url, array $options = []) use ($expectedMessagesTranslationsContent): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $options['normalized_headers']['crowdin-api-filename'][0]);
                $this->assertStringMatchesFormat($expectedMessagesTranslationsContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 20]], ['http_code' => 201]);
            },
            'importTranslations' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/imports', $url);
                $this->assertSame('{"storageId":20,"languageIds":["fr"],"fileId":199}', $options['body']);

                return new JsonMockResponse(['data' => ['identifier' => 'b8bfd653-2882-4b81-b74f-edae8b492b11']], ['http_code' => 202]);
            },
            'checkImport' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/imports/b8bfd653-2882-4b81-b74f-edae8b492b11', $url);

                return new JsonMockResponse(['data' => ['status' => 'finished']]);
            },
        ];

        $httpClient = (new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]);

        $provider = self::createProvider(
            $httpClient,
            new XliffFileLoader(),
            $this->getLogger(),
            $this->getDefaultLocale(),
            'api.crowdin.com',
            '1',
        );

        $provider->write($translatorBag);

        $this->assertSame(\count($responses), $httpClient->getRequestsCount());
    }

    #[DataProvider('getResponsesForOneLocaleAndOneDomain')]
    public function testReadForOneLocaleAndOneDomain(string $locale, string $domain, string $responseContent, TranslatorBag $expectedTranslatorBag, string $expectedTargetLanguageId)
    {
        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        ['data' => [
                            'id' => 12,
                            'name' => 'messages.xlf',
                        ]],
                    ],
                ]);
            },
            'getProject' => function (string $method, string $url) use ($locale): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => [str_replace('_', '-', $locale)],
                    'languageMapping' => [],
                ]]);
            },
            'exportProjectTranslations' => function (string $method, string $url, array $options = []) use ($expectedTargetLanguageId): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/exports', $url);
                $this->assertSame(\sprintf('{"targetLanguageId":"%s","fileIds":[12]}', $expectedTargetLanguageId), $options['body']);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url) use ($responseContent): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse($responseContent);
            },
        ];

        $this->loader = $this->createMock(LoaderInterface::class);
        $this->loader->expects($this->once())
            ->method('load')
            ->willReturn($expectedTranslatorBag->getCatalogue($locale));

        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $translatorBag = $crowdinProvider->read([$domain], [$locale]);

        $this->assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
    }

    public static function getResponsesForOneLocaleAndOneDomain(): \Generator
    {
        $arrayLoader = new ArrayLoader();

        $expectedTranslatorBagFr = new TranslatorBag();
        $expectedTranslatorBagFr->addCatalogue($arrayLoader->load([
            'index.hello' => 'Bonjour',
            'index.greetings' => 'Bienvenue, {firstname} !',
        ], 'fr'));

        yield ['fr', 'messages', <<<'XLIFF'
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="fr" datatype="database" tool-id="crowdin">
                <header>
                  <tool tool-id="crowdin" tool-name="Crowdin" tool-version="1.0.25 20201211-1" tool-company="Crowdin"/>
                </header>
                <body>
                  <trans-unit id="crowdin:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
                    <source>index.hello</source>
                    <target state="translated">Bonjour</target>
                  </trans-unit>
                  <trans-unit id="crowdin:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="crowdin:format=icu">
                    <source>index.greetings</source>
                    <target state="translated">Bienvenue, {firstname} !</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>
            XLIFF,
            $expectedTranslatorBagFr, 'fr',
        ];

        $expectedTranslatorBagEnUs = new TranslatorBag();
        $expectedTranslatorBagEnUs->addCatalogue($arrayLoader->load([
            'index.hello' => 'Hello',
            'index.greetings' => 'Welcome, {firstname}!',
        ], 'en_GB'));

        yield ['en_GB', 'messages', <<<'XLIFF'
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en_GB" datatype="database" tool-id="crowdin">
                <header>
                  <tool tool-id="crowdin" tool-name="Crowdin" tool-version="1.0.25 20201211-1" tool-company="Crowdin"/>
                </header>
                <body>
                  <trans-unit id="crowdin:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
                    <source>index.hello</source>
                    <target state="translated">Hello</target>
                  </trans-unit>
                  <trans-unit id="crowdin:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="crowdin:format=icu">
                    <source>index.greetings</source>
                    <target state="translated">Welcome, {firstname}!</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>
            XLIFF,
            $expectedTranslatorBagEnUs, 'en-GB',
        ];
    }

    #[DataProvider('getResponsesForDefaultLocaleAndOneDomain')]
    public function testReadForDefaultLocaleAndOneDomain(string $locale, string $domain, string $responseContent, TranslatorBag $expectedTranslatorBag)
    {
        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        ['data' => [
                            'id' => 12,
                            'name' => 'messages.xlf',
                        ]],
                    ],
                ]);
            },
            'getProject' => function (string $method, string $url) use ($locale): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => $locale,
                    'targetLanguageIds' => [],
                    'languageMapping' => [],
                ]]);
            },
            'downloadSource' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12/download', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url) use ($responseContent): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse($responseContent);
            },
        ];

        $this->loader = $this->createMock(LoaderInterface::class);
        $this->loader->expects($this->once())
            ->method('load')
            ->willReturn($expectedTranslatorBag->getCatalogue($locale));

        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $translatorBag = $crowdinProvider->read([$domain], [$locale]);

        $this->assertEquals($expectedTranslatorBag->getCatalogues(), $translatorBag->getCatalogues());
    }

    public static function getResponsesForDefaultLocaleAndOneDomain(): \Generator
    {
        $arrayLoader = new ArrayLoader();

        $expectedTranslatorBagEn = new TranslatorBag();
        $expectedTranslatorBagEn->addCatalogue($arrayLoader->load([
            'index.hello' => 'Hello',
            'index.greetings' => 'Welcome, {firstname} !',
        ], 'en', 'messages'));

        yield ['en', 'messages', <<<'XLIFF'
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="fr" datatype="plaintext" tool-id="crowdin">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="crowdin:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
                    <source>index.hello</source>
                    <target state="translated">Hello</target>
                  </trans-unit>
                  <trans-unit id="crowdin:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="crowdin:format=icu">
                    <source>index.greetings</source>
                    <target state="translated">Welcome, {firstname} !</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>
            XLIFF,
            $expectedTranslatorBagEn,
        ];
    }

    public function testReadWithoutDomains()
    {
        $responses = [
            'listFiles' => new JsonMockResponse(['data' => [
                ['data' => [
                    'id' => 12,
                    'name' => 'messages.xlf',
                ]],
                ['data' => [
                    'id' => 13,
                    'name' => 'validators.xlf',
                ]],
            ]]),
            'getProject' => new JsonMockResponse(['data' => [
                'sourceLanguageId' => 'en',
                'targetLanguageIds' => [],
                'languageMapping' => [],
            ]]),
            'exportMessagesSource' => new JsonMockResponse(['data' => ['url' => 'https://messages.en']]),
            'exportValidatorsSource' => new JsonMockResponse(['data' => ['url' => 'https://validators.en']]),
            'downloadFirstSource' => new MockResponse(<<<'XLIFF'
                <?xml version="1.0" encoding="UTF-8"?>
                <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
                  <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                    <body>
                        <trans-unit id="crowdin:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
                            <source>index.hello</source>
                            <target state="translated">Hello</target>
                        </trans-unit>
                    </body>
                  </file>
                </xliff>
                XLIFF),
            'downloadSecondSource' => new MockResponse(<<<'XLIFF'
                <?xml version="1.0" encoding="UTF-8"?>
                <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
                  <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                    <body>
                        <trans-unit id="%s" resname="post.num_comments">
                            <source>post.num_comments</source>
                            <target>{count, plural, one {# comment} other {# comments}}</target>
                        </trans-unit>
                    </body>
                  </file>
                </xliff>
                XLIFF),
        ];

        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $translatorBag = $crowdinProvider->read([], ['en']);

        $this->assertEqualsCanonicalizing(['messages', 'validators'], $translatorBag->getCatalogue('en')->getDomains());
    }

    public function testReadUnknownDomain()
    {
        $responses = [
            'listFiles' => new JsonMockResponse(['data' => [
                ['data' => [
                    'id' => 12,
                    'name' => 'messages.xlf',
                ]],
            ]]),
            'getProject' => new JsonMockResponse(['data' => [
                'sourceLanguageId' => 'en',
                'targetLanguageIds' => [],
                'languageMapping' => [],
            ]]),
        ];

        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $this->assertSame([], $crowdinProvider->read(['unknown'], ['en'])->getCatalogues());
    }

    public function testReadWithoutLocales()
    {
        $responses = [
            'listFiles' => new JsonMockResponse(['data' => [
                ['data' => [
                    'id' => 12,
                    'name' => 'messages.xlf',
                ]],
            ]]),
            'getProject' => new JsonMockResponse(['data' => [
                'sourceLanguageId' => 'en',
                'targetLanguageIds' => ['fr-BE'],
                'languageMapping' => [],
            ]]),
            'exportSource' => new JsonMockResponse(['data' => ['url' => 'https://file.en']]),
            'exportTranslations' => new JsonMockResponse(['data' => ['url' => 'https://file.fr']]),
            'downloadSource' => new MockResponse(<<<'XLIFF'
                <?xml version="1.0" encoding="UTF-8"?>
                <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
                  <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                    <body></body>
                  </file>
                </xliff>
                XLIFF),
            'downloadTranslations' => new MockResponse(<<<'XLIFF'
                <?xml version="1.0" encoding="UTF-8"?>
                <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
                  <file source-language="en" target-language="fr-BE" datatype="plaintext" original="file.ext">
                    <body></body>
                  </file>
                </xliff>
                XLIFF),
        ];

        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $translatorBag = $crowdinProvider->read(['messages'], []);

        $this->assertEqualsCanonicalizing(
            ['en', 'fr_BE'],
            array_map(static fn (MessageCatalogue $catalogue) => $catalogue->getLocale(), $translatorBag->getCatalogues()),
        );
    }

    public function testMappedLanguageIdCanStillBeRead()
    {
        $responses = [
            'listFiles' => new JsonMockResponse(['data' => [
                ['data' => [
                    'id' => 12,
                    'name' => 'messages.xlf',
                ]],
            ]]),
            'getProject' => new JsonMockResponse(['data' => [
                'sourceLanguageId' => 'fr',
                'targetLanguageIds' => [],
                'languageMapping' => ['fr' => ['locale' => 'fr_FR']],
            ]]),
            'exportSource' => new JsonMockResponse(['data' => ['url' => 'https://file.fr']]),
            'downloadSource' => new MockResponse(<<<'XLIFF'
                <?xml version="1.0" encoding="UTF-8"?>
                <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
                  <file source-language="fr" target-language="fr" datatype="plaintext" original="file.ext">
                    <body></body>
                  </file>
                </xliff>
                XLIFF),
        ];

        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $translatorBag = $crowdinProvider->read(['messages'], ['fr']);

        $this->assertCount(1, $catalogues = $translatorBag->getCatalogues());
        $this->assertSame('fr', $catalogues[0]->getLocale());
    }

    #[TestWith(['uk'])]
    #[TestWith(['uk_UA'])]
    public function testMappedLanguageCanBeReadUnderBothNames(string $locale)
    {
        $responses = [
            'listFiles' => new JsonMockResponse(['data' => [
                ['data' => [
                    'id' => 12,
                    'name' => 'messages.xlf',
                ]],
            ]]),
            'getProject' => new JsonMockResponse(['data' => [
                'sourceLanguageId' => 'en',
                'targetLanguageIds' => ['uk'],
                'languageMapping' => ['uk' => ['locale' => 'uk-UA']],
            ]]),
            'exportTranslations' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/exports', $url);
                $this->assertSame(json_encode(['targetLanguageId' => 'uk', 'fileIds' => [12]]), $options['body']);

                return new JsonMockResponse(['data' => ['url' => 'https://file.uk']]);
            },
            'downloadTranslations' => new MockResponse(<<<'XLIFF'
                <?xml version="1.0" encoding="UTF-8"?>
                <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
                  <file source-language="en" target-language="uk-UA" datatype="plaintext" original="file.ext">
                    <body></body>
                  </file>
                </xliff>
                XLIFF),
        ];

        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $translatorBag = $crowdinProvider->read(['messages'], [$locale]);

        $this->assertCount(1, $catalogues = $translatorBag->getCatalogues());
        $this->assertSame($locale, $catalogues[0]->getLocale());
    }

    public function testReadWithoutLocalesReadsMappedLanguagesOnce()
    {
        $responses = [
            'listFiles' => new JsonMockResponse(['data' => [
                ['data' => [
                    'id' => 12,
                    'name' => 'messages.xlf',
                ]],
            ]]),
            'getProject' => new JsonMockResponse(['data' => [
                'sourceLanguageId' => 'en',
                'targetLanguageIds' => ['uk'],
                'languageMapping' => ['uk' => ['locale' => 'uk-UA']],
            ]]),
            'downloadSource' => new JsonMockResponse(['data' => ['url' => 'https://file.en']]),
            'exportTranslations' => new JsonMockResponse(['data' => ['url' => 'https://file.uk']]),
            'getSourceFile' => new MockResponse(<<<'XLIFF'
                <?xml version="1.0" encoding="UTF-8"?>
                <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
                  <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                    <body></body>
                  </file>
                </xliff>
                XLIFF),
            'getTranslationsFile' => new MockResponse(<<<'XLIFF'
                <?xml version="1.0" encoding="UTF-8"?>
                <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
                  <file source-language="en" target-language="uk-UA" datatype="plaintext" original="file.ext">
                    <body></body>
                  </file>
                </xliff>
                XLIFF),
        ];

        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $translatorBag = $crowdinProvider->read(['messages'], []);

        $this->assertEqualsCanonicalizing(
            ['en', 'uk_UA'],
            array_map(static fn (MessageCatalogue $catalogue) => $catalogue->getLocale(), $translatorBag->getCatalogues()),
        );
    }

    public function testSourceLanguageIsNeverExported()
    {
        $responses = [
            'listFiles' => new JsonMockResponse(['data' => [
                ['data' => [
                    'id' => 12,
                    'name' => 'messages.xlf',
                ]],
            ]]),
            'getProject' => new JsonMockResponse(['data' => [
                'sourceLanguageId' => 'en',
                'targetLanguageIds' => [],
                'languageMapping' => [],
            ]]),
            'downloadSource' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12/download', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.en']]);
            },
            'getSourceFile' => new MockResponse(<<<'XLIFF'
                <?xml version="1.0" encoding="UTF-8"?>
                <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
                  <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                    <body></body>
                  </file>
                </xliff>
                XLIFF),
        ];

        // the project source language is not the application default locale
        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), 'en_US', 'api.crowdin.com', '1');

        $translatorBag = $crowdinProvider->read(['messages'], []);

        $this->assertCount(1, $catalogues = $translatorBag->getCatalogues());
        $this->assertSame('en', $catalogues[0]->getLocale());
    }

    public function testReadServerException()
    {
        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        ['data' => [
                            'id' => 12,
                            'name' => 'messages.xlf',
                        ]],
                    ],
                ]);
            },
            'getProject' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => ['fr'],
                    'languageMapping' => [],
                ]]);
            },
            'exportProjectTranslations' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/exports', $url);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to export file.');

        $crowdinProvider->read(['messages'], ['fr']);
    }

    public function testReadDownloadServerException()
    {
        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        ['data' => [
                            'id' => 12,
                            'name' => 'messages.xlf',
                        ]],
                    ],
                ]);
            },
            'getProject' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1', $url);

                return new JsonMockResponse(['data' => [
                    'sourceLanguageId' => 'en',
                    'targetLanguageIds' => ['fr'],
                    'languageMapping' => [],
                ]]);
            },
            'exportProjectTranslations' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/translations/exports', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $crowdinProvider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unable to download file content.');

        $crowdinProvider->read(['messages'], ['fr']);
    }

    public function testDelete()
    {
        $sourceFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%1$s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                  <trans-unit id="%2$s" resname="b">
                    <source>b</source>
                    <target>trans_en_b</target>
                  </trans-unit>
                  <trans-unit id="%3$s" resname="c">
                    <source>c</source>
                    <target>trans_en_c</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $storageFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        [
                            'data' => [
                                'id' => 12,
                                'name' => 'messages.xlf',
                            ],
                        ],
                    ],
                ]);
            },
            'downloadSource' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12/download', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url) use ($sourceFileContent): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse($sourceFileContent);
            },
            'addStorage' => function (
                string $method,
                string $url,
                array $options,
            ) use ($storageFileContent): ResponseInterface {
                $contentType = $options['normalized_headers']['content-type'][0];
                $crowdinApiFileName = $options['normalized_headers']['crowdin-api-filename'][0];

                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $contentType);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $crowdinApiFileName);
                $this->assertStringMatchesFormat($storageFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'updateFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('PUT', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12', $url);
                $this->assertSame('{"storageId":19}', $options['body']);

                return new JsonMockResponse(['data' => ['id' => 12, 'name' => 'messages.xlf']]);
            },
        ];

        $deletedStrings = new TranslatorBag();
        $deletedStrings->addCatalogue(
            new MessageCatalogue(
                'en',
                [
                    'messages' => [
                        'b' => 'trans_en_b',
                        'c' => 'trans_en_c',
                    ],
                ]
            )
        );

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $provider->delete($deletedStrings);
    }

    public function testDeleteUpdateFileReturnsNull()
    {
        $sourceFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%1$s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                  <trans-unit id="%2$s" resname="b">
                    <source>b</source>
                    <target>trans_en_b</target>
                  </trans-unit>
                  <trans-unit id="%3$s" resname="c">
                    <source>c</source>
                    <target>trans_en_c</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $storageFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        [
                            'data' => [
                                'id' => 12,
                                'name' => 'messages.xlf',
                            ],
                        ],
                    ],
                ]);
            },
            'downloadSource' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12/download', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url) use ($sourceFileContent): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse($sourceFileContent);
            },
            'addStorage' => function (
                string $method,
                string $url,
                array $options,
            ) use ($storageFileContent): ResponseInterface {
                $contentType = $options['normalized_headers']['content-type'][0];
                $crowdinApiFileName = $options['normalized_headers']['crowdin-api-filename'][0];

                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $contentType);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $crowdinApiFileName);
                $this->assertStringMatchesFormat($storageFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'updateFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('PUT', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12', $url);
                $this->assertSame('{"storageId":19}', $options['body']);

                return new MockResponse('', ['http_code' => 404]);
            },
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Unable to update file in Crowdin for file ID "12" and domain "messages": "".');
        $logger->expects(self::once())
            ->method('warning')
            ->with('Unable to update file "12" and domain "messages".');

        $deletedStrings = new TranslatorBag();
        $deletedStrings->addCatalogue(
            new MessageCatalogue(
                'en',
                [
                    'messages' => [
                        'b' => 'trans_en_b',
                        'c' => 'trans_en_c',
                    ],
                ]
            )
        );

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $logger, $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $provider->delete($deletedStrings);
    }

    public function testDeleteUpdateFileServerException()
    {
        $sourceFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%1$s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                  <trans-unit id="%2$s" resname="b">
                    <source>b</source>
                    <target>trans_en_b</target>
                  </trans-unit>
                  <trans-unit id="%3$s" resname="c">
                    <source>c</source>
                    <target>trans_en_c</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $storageFileContent = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="%s" resname="a">
                    <source>a</source>
                    <target>trans_en_a</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;

        $responses = [
            'listFiles' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files', $url);

                return new JsonMockResponse([
                    'data' => [
                        [
                            'data' => [
                                'id' => 12,
                                'name' => 'messages.xlf',
                            ],
                        ],
                    ],
                ]);
            },
            'downloadSource' => function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12/download', $url);

                return new JsonMockResponse(['data' => ['url' => 'https://file.url']]);
            },
            'downloadFile' => function (string $method, string $url) use ($sourceFileContent): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertSame('https://file.url/', $url);

                return new MockResponse($sourceFileContent);
            },
            'addStorage' => function (
                string $method,
                string $url,
                array $options,
            ) use ($storageFileContent): ResponseInterface {
                $contentType = $options['normalized_headers']['content-type'][0];
                $crowdinApiFileName = $options['normalized_headers']['crowdin-api-filename'][0];

                $this->assertSame('POST', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/storages', $url);
                $this->assertSame('Content-Type: application/octet-stream', $contentType);
                $this->assertSame('Crowdin-API-FileName: messages.xlf', $crowdinApiFileName);
                $this->assertStringMatchesFormat($storageFileContent, $options['body']);

                return new JsonMockResponse(['data' => ['id' => 19]], ['http_code' => 201]);
            },
            'updateFile' => function (string $method, string $url, array $options = []): ResponseInterface {
                $this->assertSame('PUT', $method);
                $this->assertSame('https://api.crowdin.com/api/v2/projects/1/files/12', $url);
                $this->assertSame('{"storageId":19}', $options['body']);

                return new MockResponse('', ['http_code' => 500]);
            },
        ];

        $deletedStrings = new TranslatorBag();
        $deletedStrings->addCatalogue(
            new MessageCatalogue(
                'en',
                [
                    'messages' => [
                        'b' => 'trans_en_b',
                        'c' => 'trans_en_c',
                    ],
                ]
            )
        );

        $provider = self::createProvider((new MockHttpClient($responses))->withOptions([
            'base_uri' => 'https://api.crowdin.com/api/v2/',
            'auth_bearer' => 'API_TOKEN',
        ]), $this->getLoader(), $this->getLogger(), $this->getDefaultLocale(), 'api.crowdin.com', '1');

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage(
            'Unable to update file "12" and domain "messages": '.
            '"Unable to update file in Crowdin for file ID "12" and domain "messages"."'
        );

        $provider->delete($deletedStrings);
    }
}
