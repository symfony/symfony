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

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKms;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKmsFactory;
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
}
