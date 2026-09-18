<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\HarEntryNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Recorder\Matcher\DefaultMatcher;
use Symfony\Component\HttpClient\Recorder\RecorderConfiguration;
use Symfony\Component\HttpClient\Recorder\RecorderMode;
use Symfony\Component\HttpClient\Recorder\Redactor\DefaultRedactor;
use Symfony\Component\HttpClient\Recorder\Store\FilesystemStore;
use Symfony\Component\HttpClient\RecorderHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Test\HarFileResponseFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class RecorderHttpClientTest extends TestCase
{
    private string $harFile;

    protected function setUp(): void
    {
        $this->harFile = sys_get_temp_dir().'/'.uniqid('recorder_http_client_test_', true).'.har';
    }

    protected function tearDown(): void
    {
        @unlink($this->harFile);
        @unlink($this->harFile.'.lock');
    }

    public function testReplayMissingFileThrowsWithoutTouchingTheNetwork()
    {
        $inner = new class implements HttpClientInterface {
            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                throw new \LogicException('The network must never be reached in replay mode.');
            }

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                throw new \LogicException('not implemented');
            }

            public function withOptions(array $options): static
            {
                return $this;
            }
        };

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));

        $this->expectException(HarEntryNotFoundException::class);

        $recorder->request('GET', 'https://example.com/missing');
    }

    public function testRecordThenReplayRoundTrip()
    {
        $inner = new MockHttpClient(new MockResponse('{"ok":true}', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]));

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        $response = $recorder->request('GET', 'https://example.com/ok');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"ok":true}', $response->getContent());

        $this->assertFileExists($this->harFile);

        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('symfony/http-client', $har['log']['creator']['name']);
        $this->assertIsString($har['log']['creator']['version']);
        $this->assertNotSame('', $har['log']['creator']['version']);

        $replay = new RecorderHttpClient(new MockHttpClient(), new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));

        $replayed = $replay->request('GET', 'https://example.com/ok');
        $this->assertSame(200, $replayed->getStatusCode());
        $this->assertSame('{"ok":true}', $replayed->getContent());
    }

    public function testReplayRecordsOnMissWhenRecordIfMissingIsEnabled()
    {
        $inner = new MockHttpClient(new MockResponse('recorded', ['http_code' => 200]));

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile, true));

        $response = $recorder->request('GET', 'https://example.com/first-run');

        $this->assertSame('recorded', $response->getContent());
        $this->assertFileExists($this->harFile);
    }

    public function testRedactsSensitiveHeadersAndQueryAndBodyOnRecordButStaysReplayable()
    {
        $inner = new MockHttpClient(new MockResponse('secret-response', ['http_code' => 200]));

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        $options = [
            'headers' => ['Authorization' => 'Bearer super-secret-token'],
            'body' => json_encode(['username' => 'bob', 'password' => 'hunter2']),
        ];

        $response = $recorder->request('POST', 'https://example.com/login?token=abc123&foo=bar', $options);
        $response->getContent(); // force the recording to be flushed

        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $entry = $har['log']['entries'][0];

        $this->assertStringNotContainsString('abc123', $entry['request']['url']);
        $this->assertStringContainsString('foo=bar', $entry['request']['url']);

        $authHeader = current(array_filter($entry['request']['headers'], static fn ($h) => 'authorization' === $h['name']));
        $this->assertNotFalse($authHeader);
        $this->assertStringNotContainsString('super-secret-token', $authHeader['value']);

        $storedBody = json_decode($entry['request']['postData']['text'], true);
        $this->assertSame('bob', $storedBody['username']);
        $this->assertStringNotContainsString('hunter2', $entry['request']['postData']['text']);

        // replaying the exact same live request (with its real secrets) must still match
        $replay = new RecorderHttpClient(new MockHttpClient(), new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));

        $replayed = $replay->request('POST', 'https://example.com/login?token=abc123&foo=bar', $options);
        $this->assertSame('secret-response', $replayed->getContent());
    }

    public function testStreamDoesNotThrow()
    {
        $inner = new MockHttpClient(new MockResponse('streamed', ['http_code' => 200]));
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Passthrough, $this->harFile));

        $response = $recorder->request('GET', 'https://example.com/stream');

        $chunks = '';
        foreach ($recorder->stream($response) as $chunk) {
            $chunks .= $chunk->getContent();
        }

        $this->assertSame('streamed', $chunks);
    }

    public function testReplayMissingEntryThrowsDedicatedException()
    {
        $inner = new MockHttpClient(new MockResponse('recorded', ['http_code' => 200]));

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        // Record one entry
        $recorder->request('GET', 'https://example.com/a')->getContent();

        $replay = new RecorderHttpClient(new MockHttpClient(), new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));

        $this->expectException(HarEntryNotFoundException::class);
        $replay->request('GET', 'https://example.com/other');
    }

    public function testReplayStreamsSeveralResponsesTogether()
    {
        $inner = new MockHttpClient([new MockResponse('a', ['http_code' => 200]), new MockResponse('b', ['http_code' => 200])]);

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        // Record two entries
        $recorder->request('GET', 'https://example.com/a')->getContent();
        $recorder->request('GET', 'https://example.com/b')->getContent();

        $replay = new RecorderHttpClient(new MockHttpClient(), new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));

        $r1 = $replay->request('GET', 'https://example.com/a');
        $r2 = $replay->request('GET', 'https://example.com/b');

        $result = [];
        foreach ($replay->stream([$r1, $r2]) as $response => $chunk) {
            $url = $response->getInfo('url');
            if (!isset($result[$url])) {
                $result[$url] = '';
            }
            $result[$url] .= $chunk->getContent();
        }

        $this->assertSame(['https://example.com/a' => 'a', 'https://example.com/b' => 'b'], $result);
    }

    public function testRecordStreamsSeveralResponsesTogether()
    {
        $inner = new MockHttpClient([new MockResponse('a', ['http_code' => 200]), new MockResponse('b', ['http_code' => 200])]);

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        $r1 = $recorder->request('GET', 'https://example.com/a');
        $r2 = $recorder->request('GET', 'https://example.com/b');

        $result = [];
        foreach ($recorder->stream([$r1, $r2]) as $response => $chunk) {
            $url = $response->getInfo('url');
            if (!isset($result[$url])) {
                $result[$url] = '';
            }
            $result[$url] .= $chunk->getContent();
        }

        $this->assertSame(['https://example.com/a' => 'a', 'https://example.com/b' => 'b'], $result);

        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertCount(2, $har['log']['entries']);
    }

    public function testRecordsBinaryBodiesAsBase64()
    {
        $binaryBody = "\xFF\xFE\x00bin";
        $inner = new MockHttpClient(new MockResponse($binaryBody, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/octet-stream'],
        ]));

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $response = $recorder->request('GET', 'https://example.com/bin');
        $content = $response->getContent();

        $this->assertSame($binaryBody, $content);

        // Check the stored entry
        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $entry = $har['log']['entries'][0];

        $this->assertArrayHasKey('encoding', $entry['response']['content']);
        $this->assertSame('base64', $entry['response']['content']['encoding']);
        $this->assertSame($binaryBody, base64_decode($entry['response']['content']['text']));

        // Test replay
        $replay = new RecorderHttpClient(new MockHttpClient(), new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));
        $replayed = $replay->request('GET', 'https://example.com/bin');
        $this->assertSame($binaryBody, $replayed->getContent());
    }

    public function testJsonOptionIsRecordedAsRequestBody()
    {
        $inner = new MockHttpClient([
            new MockResponse('one', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
            new MockResponse('two', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
        ]);

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        // Record two different POST requests with json option
        $recorder->request('POST', 'https://example.com/items', ['json' => ['a' => 1]])->getContent();
        $recorder->request('POST', 'https://example.com/items', ['json' => ['a' => 2]])->getContent();

        // Check the stored entries
        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $entries = $har['log']['entries'];

        $this->assertCount(2, $entries);
        $this->assertSame('{"a":1}', $entries[0]['request']['postData']['text']);
        $this->assertSame('application/json', $entries[0]['request']['postData']['mimeType']);
        $this->assertSame('{"a":2}', $entries[1]['request']['postData']['text']);
        $this->assertSame('application/json', $entries[1]['request']['postData']['mimeType']);

        // Test replay with specific json option
        $replay = new RecorderHttpClient(new MockHttpClient(), new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));
        $replayed = $replay->request('POST', 'https://example.com/items', ['json' => ['a' => 2]]);
        $this->assertSame('two', $replayed->getContent());
    }

    public function testArrayBodyIsRecordedAsFormEncoded()
    {
        $inner = new MockHttpClient(new MockResponse('form-response', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'text/html'],
        ]));

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $recorder->request('POST', 'https://example.com/form', ['body' => ['x' => 'y']])->getContent();

        // Check the stored entry
        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $entry = $har['log']['entries'][0];

        $this->assertSame('x=y', $entry['request']['postData']['text']);
        $this->assertSame('application/x-www-form-urlencoded', $entry['request']['postData']['mimeType']);
    }

    public function testRelativeUrlIsResolvedWithDefaultOptions()
    {
        $inner = new MockHttpClient(new MockResponse('relative-response', [
            'http_code' => 200,
        ]));

        // Record with base_uri in default options
        $recorder = new RecorderHttpClient(
            $inner,
            new FilesystemStore(),
            new RecorderConfiguration(RecorderMode::Record, $this->harFile),
            new DefaultMatcher(),
            new DefaultRedactor(),
            ['base_uri' => 'https://example.com']
        );

        $recorder->request('GET', '/rel')->getContent();

        // Check the stored entry has the resolved URL
        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $entry = $har['log']['entries'][0];
        $this->assertSame('https://example.com/rel', $entry['request']['url']);

        // Test replay with same default options
        $replay = new RecorderHttpClient(
            new MockHttpClient(),
            new FilesystemStore(),
            new RecorderConfiguration(RecorderMode::Replay, $this->harFile),
            new DefaultMatcher(),
            new DefaultRedactor(),
            ['base_uri' => 'https://example.com']
        );

        $replayed = $replay->request('GET', '/rel');
        $this->assertSame('relative-response', $replayed->getContent());
    }

    public function testAuthBearerIsRecordedAsRedactedAuthorizationHeader()
    {
        $inner = new MockHttpClient(new MockResponse('auth-response', [
            'http_code' => 200,
        ]));

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $recorder->request('GET', 'https://example.com/auth', ['auth_bearer' => 'tok'])->getContent();

        // Check the stored entry
        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $entry = $har['log']['entries'][0];

        $authHeader = null;
        foreach ($entry['request']['headers'] as $header) {
            if ('authorization' === strtolower($header['name'])) {
                $authHeader = $header;
                break;
            }
        }

        $this->assertNotNull($authHeader);
        $this->assertStringContainsString('[REDACTED]', $authHeader['value']);
        $this->assertStringNotContainsString('tok', $authHeader['value']);
    }

    public function testRedactsTokenEndpointResponseBodyOnRecord()
    {
        $inner = new MockHttpClient(new MockResponse('{"access_token":"tok","token_type":"Bearer"}', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]));

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        $response = $recorder->request('POST', 'https://example.com/token');
        $response->getContent(); // force the recording to be flushed

        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $entry = $har['log']['entries'][0];
        $storedBody = json_decode($entry['response']['content']['text'], true);

        $this->assertSame('[REDACTED]', $storedBody['access_token']);
        $this->assertSame('Bearer', $storedBody['token_type']);
    }

    public function testReplayMissingEntryDoesNotRecordWhenRecordIfMissingIsDisabled()
    {
        $inner = new MockHttpClient(new MockResponse('recorded', ['http_code' => 200]));

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        // Record one entry
        $recorder->request('GET', 'https://example.com/a')->getContent();

        $innerThatThrows = new class implements HttpClientInterface {
            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                throw new \LogicException('The network must not be reached when recordIfMissing is false.');
            }

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                throw new \LogicException('not implemented');
            }

            public function withOptions(array $options): static
            {
                return $this;
            }
        };

        $replay = new RecorderHttpClient($innerThatThrows, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));

        $this->expectException(HarEntryNotFoundException::class);
        $replay->request('GET', 'https://example.com/b');
    }

    public function testConfigurationIsReadOnEveryRequest()
    {
        $inner = new MockHttpClient([
            new MockResponse('live', ['http_code' => 200]),
            new MockResponse('recorded', ['http_code' => 200]),
        ]);

        $harFile = $this->harFile;

        $config = new class($harFile) implements \Symfony\Component\HttpClient\Recorder\RecorderConfigurationInterface {
            public RecorderMode $mode = RecorderMode::Passthrough;

            public function __construct(private string $harFilePath)
            {
            }

            public function getMode(): RecorderMode
            {
                return $this->mode;
            }

            public function getHarFilePath(): string
            {
                return $this->harFilePath;
            }

            public function shouldRecordIfMissing(): bool
            {
                return false;
            }
        };

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), $config);

        // First request with Passthrough mode
        $response1 = $recorder->request('GET', 'https://example.com/test');
        $this->assertSame('live', $response1->getContent());
        $this->assertFileDoesNotExist($this->harFile);

        // Change mode to Record
        $config->mode = RecorderMode::Record;

        // Second request should now record
        $response2 = $recorder->request('GET', 'https://example.com/test2');
        $response2->getContent(); // force the recording to be flushed
        $this->assertFileExists($this->harFile);
    }

    public function testPassthroughDoesNotNormalizeOptions()
    {
        $inner = new class extends MockHttpClient {
            public array $capturedOptions = [];

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                // capture the options before the inner client normalizes them
                $this->capturedOptions = $options;

                return parent::request($method, $url, $options);
            }
        };

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Passthrough, $this->harFile));

        $recorder->request('GET', 'https://example.com/x', ['headers' => ['X-Foo' => 'bar']])->getContent();

        $this->assertArrayNotHasKey('normalized_headers', $inner->capturedOptions);
    }

    public function testReplayedJsonBodyIsByteIdenticalWhenNothingWasRedacted()
    {
        $body = '{"url":"https://x/y","name":"caf\u00e9"}';

        $inner = new MockHttpClient(new MockResponse($body, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]));

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $recorder->request('GET', 'https://example.com/test')->getContent();

        $replay = new RecorderHttpClient(new MockHttpClient(), new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));
        $replayed = $replay->request('GET', 'https://example.com/test');

        $this->assertSame($body, $replayed->getContent());
    }

    public function testReplayMatchesWhenTheQueryOptionIsUsed()
    {
        $inner = new MockHttpClient(new MockResponse('payload', ['http_code' => 200]));
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $recorder->request('GET', 'https://example.com/x', ['query' => ['token' => 'secret', 'foo' => 'bar']])->getContent();

        $replay = new RecorderHttpClient(new MockHttpClient(), new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));

        $response = $replay->request('GET', 'https://example.com/x', ['query' => ['token' => 'secret', 'foo' => 'bar']]);

        $this->assertSame('payload', $response->getContent());
    }

    public function testRecordIfMissingDoesNotHitTheNetworkWhenTheFixtureExists()
    {
        // record one entry with a deny-listed query parameter
        $inner = new MockHttpClient(new MockResponse('payload', ['http_code' => 200]));
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $recorder->request('GET', 'https://example.com/x', ['query' => ['token' => 'secret']])->getContent();

        $networkCalls = 0;
        $counting = new MockHttpClient(static function () use (&$networkCalls) {
            ++$networkCalls;

            return new MockResponse('live', ['http_code' => 200]);
        });

        $replay = new RecorderHttpClient($counting, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile, true));
        $replay->request('GET', 'https://example.com/x', ['query' => ['token' => 'secret']])->getContent();

        $this->assertSame(0, $networkCalls);
    }

    public function testRecorderFileIsReplayableThroughHarFileResponseFactory()
    {
        $inner = new MockHttpClient(new MockResponse('ok', ['http_code' => 200]));
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $recorder->request('GET', 'https://example.com/x?token=secret&foo=bar', ['headers' => ['Authorization' => 'Bearer t']])->getContent();

        $client = new MockHttpClient(new HarFileResponseFactory($this->harFile, new DefaultMatcher(new DefaultRedactor())));

        $response = $client->request('GET', 'https://example.com/x?token=secret&foo=bar', ['headers' => ['Authorization' => 'Bearer t']]);

        $this->assertSame('ok', $response->getContent());
    }

    public function testSequentialResponsesToTheSameRequestAreAllRecorded()
    {
        $inner = new MockHttpClient([
            new MockResponse('server error', ['http_code' => 500]),
            new MockResponse('recovered', ['http_code' => 200]),
        ]);

        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        $recorder->request('GET', 'https://example.com/flaky')->getContent(false);
        $recorder->request('GET', 'https://example.com/flaky')->getContent(false);

        $entries = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR)['log']['entries'];

        $this->assertCount(2, $entries);
        $this->assertSame([500, 200], array_column(array_column($entries, 'response'), 'status'));
    }

    public function testSequentialResponsesAreReplayedInOrder()
    {
        $inner = new MockHttpClient([
            new MockResponse('server error', ['http_code' => 500]),
            new MockResponse('recovered', ['http_code' => 200]),
        ]);

        // Record the sequence
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $recorder->request('GET', 'https://example.com/flaky')->getContent(false);
        $recorder->request('GET', 'https://example.com/flaky')->getContent(false);

        // Replay and assert the order
        $replay = new RecorderHttpClient(new MockHttpClient(), new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile));

        $this->assertSame(500, $replay->request('GET', 'https://example.com/flaky')->getStatusCode());
        $this->assertSame(200, $replay->request('GET', 'https://example.com/flaky')->getStatusCode());
        // Third request should return 200 again (last match is reused once everything is consumed)
        $this->assertSame(200, $replay->request('GET', 'https://example.com/flaky')->getStatusCode());
    }

    public function testUnconsumedResponseIsNotRecorded()
    {
        $inner = new MockHttpClient(new MockResponse('body', ['http_code' => 200]));
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        $response = $recorder->request('GET', 'https://example.com/test');
        unset($response);

        $this->assertFileDoesNotExist($this->harFile);
    }

    public function testStatusOnlyConsumptionIsNotRecordedWithAnEmptyBody()
    {
        $inner = new MockHttpClient(new MockResponse('body', ['http_code' => 200]));
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        $response = $recorder->request('GET', 'https://example.com/test');
        $response->getStatusCode();
        unset($response);

        $this->assertFileDoesNotExist($this->harFile);
    }

    public function testWithOptionsMergesTheRecorderDefaultOptions()
    {
        $inner = new MockHttpClient(new MockResponse('body', ['http_code' => 200]));
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        $recorder = $recorder->withOptions(['base_uri' => 'https://example.com']);
        $recorder->request('GET', '/rel')->getContent();

        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $entry = $har['log']['entries'][0];
        $this->assertSame('https://example.com/rel', $entry['request']['url']);
    }

    public function testWithOptionsHeadersAreRecorded()
    {
        $inner = new MockHttpClient(new MockResponse('body', ['http_code' => 200]));
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));

        $recorder = $recorder->withOptions(['headers' => ['X-Foo' => 'bar']]);
        $recorder->request('GET', 'https://example.com/test')->getContent();

        $har = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR);
        $entry = $har['log']['entries'][0];

        $xFooHeader = null;
        foreach ($entry['request']['headers'] as $header) {
            if ('x-foo' === strtolower($header['name'])) {
                $xFooHeader = $header;
                break;
            }
        }

        $this->assertNotNull($xFooHeader);
        $this->assertSame('bar', $xFooHeader['value']);
    }

    public function testRecordModeAppendsAcrossInstances()
    {
        $inner = new MockHttpClient(new MockResponse('first', ['http_code' => 200]));
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $recorder->request('GET', 'https://example.com/first')->getContent();

        // Create a new recorder in Record mode for a different URL
        $inner2 = new MockHttpClient(new MockResponse('second', ['http_code' => 200]));
        $recorder2 = new RecorderHttpClient($inner2, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $recorder2->request('GET', 'https://example.com/second')->getContent();

        $entries = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR)['log']['entries'];

        $this->assertCount(2, $entries);
        $this->assertSame('https://example.com/first', $entries[0]['request']['url']);
        $this->assertSame('https://example.com/second', $entries[1]['request']['url']);
    }

    public function testRecordIfMissingAppendsToTheExistingFixture()
    {
        $inner = new MockHttpClient(new MockResponse('first', ['http_code' => 200]));
        $recorder = new RecorderHttpClient($inner, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Record, $this->harFile));
        $recorder->request('GET', 'https://example.com/a')->getContent();

        // Replay with recordIfMissing enabled, request a different URL
        $inner2 = new MockHttpClient(new MockResponse('second', ['http_code' => 200]));
        $replay = new RecorderHttpClient($inner2, new FilesystemStore(), new RecorderConfiguration(RecorderMode::Replay, $this->harFile, true));
        $replay->request('GET', 'https://example.com/b')->getContent();

        $entries = json_decode(file_get_contents($this->harFile), true, 512, \JSON_THROW_ON_ERROR)['log']['entries'];

        $this->assertCount(2, $entries);
    }
}
