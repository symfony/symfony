<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AwsKms\Tests;

use AsyncAws\Kms\KmsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\AwsKms\AwsKms;
use Symfony\Component\KeyManagement\Bridge\AwsKms\AwsKmsFactory;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;

class AwsKmsFactoryTest extends TestCase
{
    public function testSupportsTheAwsKmsScheme()
    {
        $factory = new AwsKmsFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('aws-kms://default?region=eu-west-1')));
        $this->assertFalse($factory->supports(Dsn::fromString('vault-transit://t@vault.local/v1/')));
    }

    public function testCreateBuildsAnAwsKmsAndConfiguresTheDefaultEndpoint()
    {
        $kms = (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://default?region=eu-west-1'));

        $this->assertInstanceOf(AwsKms::class, $kms);

        $config = self::getKmsClient($kms)->getConfiguration();
        $this->assertSame('eu-west-1', $config->get('region'));
        $this->assertTrue($config->isDefault('endpoint'));
    }

    public function testCreateForwardsExplicitCredentials()
    {
        $kms = (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://AKIA:SECRET@default?region=eu-west-1&session_token=ST'));

        $config = self::getKmsClient($kms)->getConfiguration();
        $this->assertSame('AKIA', $config->get('accessKeyId'));
        $this->assertSame('SECRET', $config->get('accessKeySecret'));
        $this->assertSame('ST', $config->get('sessionToken'));
    }

    public function testCustomEndpointIsBuiltFromHostAndPort()
    {
        $kms = (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://localhost:4566?region=eu-west-1'));

        $config = self::getKmsClient($kms)->getConfiguration();
        $this->assertSame('https://localhost:4566', $config->get('endpoint'));
    }

    public function testSchemeOptionAllowsHttpEndpointForLocalSandboxes()
    {
        $kms = (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://localhost:4566?region=eu-west-1&scheme=http'));

        $config = self::getKmsClient($kms)->getConfiguration();
        $this->assertSame('http://localhost:4566', $config->get('endpoint'));
    }

    public function testSchemeOptionRejectsArbitraryValues()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"scheme" option');
        (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://localhost:4566?region=eu-west-1&scheme=ftp'));
    }

    public function testSchemeOptionIsRejectedWithDefaultHost()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"default" host always uses the public AWS endpoint over HTTPS');
        (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://default?region=eu-west-1&scheme=http'));
    }

    public function testCreateRejectsADsnWithoutRegion()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('region');
        (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://default'));
    }

    public function testAccessKeyWithoutSecretKeyIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('secret key');
        (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://AKIA@default?region=eu-west-1'));
    }

    public function testSecretKeyWithoutAccessKeyIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('access key id');
        (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://:SECRET@default?region=eu-west-1'));
    }

    public function testUnknownDsnOptionIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option "sesion_token"');
        (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://default?region=eu-west-1&sesion_token=ST'));
    }

    public function testArrayDsnOptionIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"session_token" option');
        (new AwsKmsFactory())->create(Dsn::fromString('aws-kms://default?region=eu-west-1&session_token[]=ST'));
    }

    public function testCreateRejectsADsnWithAnUnknownScheme()
    {
        $this->expectException(UnsupportedSchemeException::class);
        (new AwsKmsFactory())->create(Dsn::fromString('vault-transit://t@vault.local/v1/'));
    }

    private static function getKmsClient(AwsKms $kms): KmsClient
    {
        $clientProperty = (new \ReflectionClass(AwsKms::class))->getProperty('client');

        return $clientProperty->getValue($kms);
    }
}
