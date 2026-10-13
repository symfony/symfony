<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKms;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKmsFactory;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\ServiceAccountTokenProvider;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;

class GoogleCloudKmsFactoryTest extends TestCase
{
    private string $credentialsPath;

    protected function setUp(): void
    {
        $this->credentialsPath = sys_get_temp_dir().'/symfony-gcp-kms-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'client_email' => 'sa@my-proj.iam.gserviceaccount.com',
            'private_key' => file_get_contents(__DIR__.'/Fixtures/private_key.pem'),
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);
    }

    public function testSupportsTheGcpScheme()
    {
        $this->assertTrue((new GoogleCloudKmsFactory())->supports(Dsn::fromString('gcp-kms://default?credentials=/tmp/sa.json')));
    }

    public function testRejectsForeignSchemes()
    {
        $this->assertFalse((new GoogleCloudKmsFactory())->supports(Dsn::fromString('aws-kms://default?region=eu-west-1')));
    }

    public function testCreateOnUnsupportedSchemeThrows()
    {
        $this->expectException(UnsupportedSchemeException::class);
        (new GoogleCloudKmsFactory())->create(Dsn::fromString('aws-kms://default?region=eu-west-1'));
    }

    public function testCreateBuildsGoogleCloudKms()
    {
        $kms = (new GoogleCloudKmsFactory())->create(Dsn::fromString('gcp-kms://default?credentials='.urlencode($this->credentialsPath)));

        $this->assertInstanceOf(GoogleCloudKms::class, $kms);
    }

    public function testHostIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('host');
        (new GoogleCloudKmsFactory())->create(new Dsn(scheme: 'gcp-kms', options: ['credentials' => $this->credentialsPath]));
    }

    public function testCredentialsOptionIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('credentials');
        (new GoogleCloudKmsFactory())->create(Dsn::fromString('gcp-kms://default'));
    }

    public function testUnknownDsnOptionIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option "credential"');
        (new GoogleCloudKmsFactory())->create(Dsn::fromString('gcp-kms://default?credential='.urlencode($this->credentialsPath)));
    }

    public function testArrayDsnOptionIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"credentials" option');
        (new GoogleCloudKmsFactory())->create(Dsn::fromString('gcp-kms://default?credentials[]='.urlencode($this->credentialsPath)));
    }

    public function testMissingCredentialsFileSurfaces()
    {
        $this->expectException(InvalidArgumentException::class);
        (new GoogleCloudKmsFactory())->create(Dsn::fromString('gcp-kms://default?credentials=/nope/missing.json'));
    }

    public function testTheGivenHttpClientIsScopedToTheDsn()
    {
        $key = 'projects/my-proj/locations/global/keyRings/app/cryptoKeys/master';
        $urls = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls, $key): MockResponse {
            $urls[] = $url;

            return str_contains($url, 'oauth2.googleapis.com')
                ? new MockResponse(json_encode(['access_token' => 'TOKEN', 'expires_in' => 3600]))
                : new MockResponse(json_encode(['name' => $key.'/cryptoKeyVersions/1', 'ciphertext' => 'CipherFromGcp']));
        });

        $kms = (new GoogleCloudKmsFactory($client))->create(Dsn::fromString('gcp-kms://kms.local:8443/v2/?credentials='.urlencode($this->credentialsPath)));
        $kms->encrypt($key, 'hello');

        $this->assertCount(2, $urls);
        $this->assertSame('https://oauth2.googleapis.com/token', $urls[0]);
        $this->assertSame('https://kms.local:8443/v2/'.$key.':encrypt', $urls[1]);
    }

    public function testTimeoutOptionsAreAppliedToTheGivenHttpClient()
    {
        $key = 'projects/my-proj/locations/global/keyRings/app/cryptoKeys/master';
        $timeouts = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$timeouts, $key): MockResponse {
            $timeouts[] = [$options['timeout'], $options['max_duration']];

            return str_contains($url, 'oauth2.googleapis.com')
                ? new MockResponse(json_encode(['access_token' => 'TOKEN', 'expires_in' => 3600]))
                : new MockResponse(json_encode(['name' => $key.'/cryptoKeyVersions/1', 'ciphertext' => 'CipherFromGcp']));
        });

        $kms = (new GoogleCloudKmsFactory($client))->create(Dsn::fromString('gcp-kms://default?credentials='.urlencode($this->credentialsPath).'&timeout=2.5&max_duration=7'));
        $kms->encrypt($key, 'hello');

        $this->assertSame([[2.5, 7.0], [2.5, 7.0]], $timeouts);
    }

    public function testTheGivenHttpClientKeepsItsOwnTimeouts()
    {
        $key = 'projects/my-proj/locations/global/keyRings/app/cryptoKeys/master';
        $timeouts = [];
        $client = (new MockHttpClient(static function (string $method, string $url, array $options) use (&$timeouts, $key): MockResponse {
            $timeouts[] = [$options['timeout'], $options['max_duration']];

            return str_contains($url, 'oauth2.googleapis.com')
                ? new MockResponse(json_encode(['access_token' => 'TOKEN', 'expires_in' => 3600]))
                : new MockResponse(json_encode(['name' => $key.'/cryptoKeyVersions/1', 'ciphertext' => 'CipherFromGcp']));
        }))->withOptions(['timeout' => 42.0, 'max_duration' => 120.0]);

        $kms = (new GoogleCloudKmsFactory($client))->create(Dsn::fromString('gcp-kms://default?credentials='.urlencode($this->credentialsPath)));
        $kms->encrypt($key, 'hello');

        $this->assertSame([[42.0, 120.0], [42.0, 120.0]], $timeouts);
    }

    public function testTheClientBuiltByTheFactoryHasDefaultTimeouts()
    {
        $kms = (new GoogleCloudKmsFactory())->create(Dsn::fromString('gcp-kms://default?credentials='.urlencode($this->credentialsPath)));

        $client = (new \ReflectionProperty(GoogleCloudKms::class, 'client'))->getValue($kms);
        $options = (new \ReflectionProperty($client, 'defaultOptions'))->getValue($client);
        $tokens = (new \ReflectionProperty(GoogleCloudKms::class, 'tokens'))->getValue($kms);

        $this->assertSame(5.0, $options['timeout']);
        $this->assertSame(10.0, $options['max_duration']);
        $this->assertSame(0, $options['max_redirects']);
        $this->assertSame($client, (new \ReflectionProperty(ServiceAccountTokenProvider::class, 'client'))->getValue($tokens));
    }

    public function testTimeoutOptionsOverrideTheDefaultsOfTheClientBuiltByTheFactory()
    {
        $kms = (new GoogleCloudKmsFactory())->create(Dsn::fromString('gcp-kms://default?credentials='.urlencode($this->credentialsPath).'&timeout=3&max_duration=60'));

        $client = (new \ReflectionProperty(GoogleCloudKms::class, 'client'))->getValue($kms);
        $options = (new \ReflectionProperty($client, 'defaultOptions'))->getValue($client);

        $this->assertSame(3.0, $options['timeout']);
        $this->assertSame(60.0, $options['max_duration']);
    }

    #[DataProvider('provideInvalidTimeoutOptions')]
    public function testInvalidTimeoutOptionIsRejected(string $query, string $option)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" option of the "gcp-kms://" DSN must be positive.', $option));
        (new GoogleCloudKmsFactory())->create(Dsn::fromString('gcp-kms://default?credentials='.urlencode($this->credentialsPath).'&'.$query));
    }

    public static function provideInvalidTimeoutOptions(): iterable
    {
        yield ['timeout=0', 'timeout'];
        yield ['timeout=-1', 'timeout'];
        yield ['timeout=abc', 'timeout'];
        yield ['timeout=1e999', 'timeout'];
        yield ['max_duration=0', 'max_duration'];
        yield ['max_duration=-5', 'max_duration'];
    }
}
