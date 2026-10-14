<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Scaleway\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Bridge\Scaleway\ScalewayKms;
use Symfony\Component\KeyManagement\Bridge\Scaleway\ScalewayKmsFactory;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;

class ScalewayKmsFactoryTest extends TestCase
{
    private const string KEY_ID = '11111111-2222-3333-4444-555555555555';

    public function testSupportsTheScalewayKmsScheme()
    {
        $this->assertTrue((new ScalewayKmsFactory())->supports(Dsn::fromString('scaleway-kms://secret@default?region=fr-par')));
    }

    public function testRejectsForeignSchemes()
    {
        $this->assertFalse((new ScalewayKmsFactory())->supports(Dsn::fromString('sodium://?keys[a]=AAAA')));
    }

    public function testCreateOnUnsupportedSchemeThrows()
    {
        $this->expectException(UnsupportedSchemeException::class);
        (new ScalewayKmsFactory())->create(Dsn::fromString('sodium://?keys[a]=AAAA'));
    }

    public function testCreateBuildsScalewayKmsWithoutAClient()
    {
        $this->assertInstanceOf(ScalewayKms::class, (new ScalewayKmsFactory())->create(Dsn::fromString('scaleway-kms://secret@default?region=fr-par')));
    }

    public function testDefaultHostTargetsThePublicApiInTheGivenRegion()
    {
        [$url, $headers] = $this->encryptThrough('scaleway-kms://scw-secret@default?region=nl-ams');

        $this->assertSame('https://api.scaleway.com/key-manager/v1alpha1/regions/nl-ams/keys/'.self::KEY_ID.'/encrypt', $url);
        $this->assertContains('X-Auth-Token: scw-secret', $headers);
    }

    public function testCustomHostIsUsedAsTheEndpoint()
    {
        [$url] = $this->encryptThrough('scaleway-kms://scw-secret@kms.example.com:8443?region=pl-waw');

        $this->assertSame('https://kms.example.com:8443/key-manager/v1alpha1/regions/pl-waw/keys/'.self::KEY_ID.'/encrypt', $url);
    }

    public static function provideInvalidDsns(): iterable
    {
        yield 'missing secret key' => ['scaleway-kms://default?region=fr-par', 'secret key'];
        yield 'project id and secret key' => ['scaleway-kms://project-id:secret@default?region=fr-par', 'The "scaleway-kms://" DSN must not include a password: put the Scaleway secret key in the user component (e.g. "scaleway-kms://<secret_key>@default?region=fr-par"); the project id is not needed.'];
        yield 'missing region' => ['scaleway-kms://secret@default', '"region" option'];
        yield 'malformed region' => ['scaleway-kms://secret@default?region=../fr-par', '"region" option'];
        yield 'unknown option' => ['scaleway-kms://secret@default?region=fr-par&zone=fr-par-1', 'Unknown option "zone"'];
        yield 'array option' => ['scaleway-kms://secret@default?region[]=fr-par', '"region" option'];
        yield 'zero timeout' => ['scaleway-kms://secret@default?region=fr-par&timeout=0', '"timeout" option'];
        yield 'non-numeric max_duration' => ['scaleway-kms://secret@default?region=fr-par&max_duration=soon', '"max_duration" option'];
    }

    #[DataProvider('provideInvalidDsns')]
    public function testInvalidDsnIsRejected(string $dsn, string $message)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new ScalewayKmsFactory())->create(Dsn::fromString($dsn));
    }

    public function testTimeoutOptionsOverrideTheClientTimeouts()
    {
        [, , $options] = $this->encryptThrough('scaleway-kms://scw-secret@default?region=fr-par&timeout=2.5&max_duration=7');

        $this->assertSame(2.5, $options['timeout']);
        $this->assertSame(7.0, $options['max_duration']);
    }

    public function testTheGivenHttpClientKeepsItsOwnTimeouts()
    {
        $timeouts = [];
        $client = (new MockHttpClient(static function (string $method, string $url, array $options) use (&$timeouts): MockResponse {
            $timeouts[] = [$options['timeout'], $options['max_duration']];

            return new MockResponse(json_encode(['key_id' => self::KEY_ID, 'ciphertext' => 'c2NhbGV3YXk=']));
        }))->withOptions(['timeout' => 42.0, 'max_duration' => 120.0]);

        (new ScalewayKmsFactory($client))->create(Dsn::fromString('scaleway-kms://scw-secret@default?region=fr-par'))->encrypt(self::KEY_ID, 'hello');

        $this->assertSame([[42.0, 120.0]], $timeouts);
    }

    public function testTheClientBuiltByTheFactoryHasDefaultTimeouts()
    {
        $kms = (new ScalewayKmsFactory())->create(Dsn::fromString('scaleway-kms://scw-secret@default?region=fr-par'));

        $client = (new \ReflectionProperty(ScalewayKms::class, 'client'))->getValue($kms);
        $options = (new \ReflectionProperty($client, 'defaultOptions'))->getValue($client);

        $this->assertSame(5.0, $options['timeout']);
        $this->assertSame(10.0, $options['max_duration']);
        $this->assertSame(0, $options['max_redirects']);
    }

    public function testTimeoutOptionsOverrideTheDefaultsOfTheClientBuiltByTheFactory()
    {
        $kms = (new ScalewayKmsFactory())->create(Dsn::fromString('scaleway-kms://scw-secret@default?region=fr-par&timeout=3&max_duration=60'));

        $client = (new \ReflectionProperty(ScalewayKms::class, 'client'))->getValue($kms);
        $options = (new \ReflectionProperty($client, 'defaultOptions'))->getValue($client);

        $this->assertSame(3.0, $options['timeout']);
        $this->assertSame(60.0, $options['max_duration']);
    }

    /**
     * @return array{string, list<string>, array<string, mixed>}
     */
    private function encryptThrough(string $dsn): array
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$url, $options['headers'], $options];

            return new MockResponse(json_encode(['key_id' => self::KEY_ID, 'ciphertext' => 'c2NhbGV3YXk=']));
        });

        (new ScalewayKmsFactory($client))->create(Dsn::fromString($dsn))->encrypt(self::KEY_ID, 'hello');

        return $captured;
    }
}
