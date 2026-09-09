<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Mailer\MailerBundle;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $this->assertSame([
            'enabled' => true,
            'message_bus' => null,
            'dsn' => null,
            'transports' => [],
            'headers' => [],
            'tracking' => [
                'opens' => null,
                'clicks' => null,
            ],
            'dkim_signer' => [
                'enabled' => false,
                'key' => '',
                'domain' => '',
                'select' => '',
                'passphrase' => '',
                'options' => [],
            ],
            'smime_signer' => [
                'enabled' => false,
                'key' => '',
                'certificate' => '',
                'passphrase' => null,
                'extra_certificates' => null,
                'sign_options' => null,
            ],
            'smime_encrypter' => [
                'enabled' => false,
                'repository' => '',
                'certificates' => [],
                'on_missing_certificate' => 'send_unencrypted',
                'encrypt_for_sender' => false,
                'cipher' => null,
            ],
            'pgp_signer' => [
                'enabled' => false,
                'secret_key' => '',
                'public_key' => null,
                'passphrase' => null,
                'binary' => 'gpg',
                'digest_algorithm' => 'SHA512',
            ],
            'pgp_encrypter' => [
                'enabled' => false,
                'repository' => '',
                'keys' => [],
                'binary' => 'gpg',
                'cipher_algorithm' => 'AES256',
                'timeout' => 60.0,
                'hide_recipients' => false,
                'on_missing_key' => 'fail',
                'encrypt_for_sender' => false,
            ],
        ], $this->process([]));
    }

    public function testATransportCanBeGivenAsAString()
    {
        $config = $this->process(['transports' => ['main' => 'smtp://example.com']]);

        $this->assertSame(['main' => ['dsn' => 'smtp://example.com', 'rate_limiter' => null]], $config['transports']);
    }

    public function testDsnAndTransportsCannotBeUsedTogether()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"dsn" and "transports" cannot be used together.');

        $this->process(['dsn' => 'smtp://example.com', 'transports' => ['main' => 'smtp://example.org']]);
    }

    public function testTheSmimeEncrypterNeedsARepositoryOrCertificates()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You must configure either "smime_encrypter.repository" or "smime_encrypter.certificates".');

        $this->process(['smime_encrypter' => ['enabled' => true]]);
    }

    public function testTheSmimeEncrypterRejectsBothARepositoryAndCertificates()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You cannot use both "smime_encrypter.repository" and "smime_encrypter.certificates" at the same time.');

        $this->process(['smime_encrypter' => ['repository' => 'my_repository', 'certificates' => ['r@example.com' => '/r.crt']]]);
    }

    public function testThePgpSignerNeedsASecretKey()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You must configure "pgp_signer.secret_key".');

        $this->process(['pgp_signer' => ['enabled' => true]]);
    }

    public function testThePgpEncrypterNeedsARepositoryOrKeys()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You must configure either "pgp_encrypter.repository" or "pgp_encrypter.keys".');

        $this->process(['pgp_encrypter' => ['enabled' => true]]);
    }

    #[RequiresPhpExtension('openssl')]
    public function testSmimeEncrypterCipherAsConstantName()
    {
        $config = $this->processSmimeEncrypter(['cipher' => 'AES_256_CBC']);
        $this->assertSame(\OPENSSL_CIPHER_AES_256_CBC, $config['smime_encrypter']['cipher']);

        $config = $this->processSmimeEncrypter(['cipher' => 'RC2_40']);
        $this->assertSame(\OPENSSL_CIPHER_RC2_40, $config['smime_encrypter']['cipher']);
    }

    #[RequiresPhpExtension('openssl')]
    public function testSmimeEncrypterCipherAsConstantValue()
    {
        $config = $this->processSmimeEncrypter(['cipher' => \OPENSSL_CIPHER_AES_256_CBC]);
        $this->assertSame(\OPENSSL_CIPHER_AES_256_CBC, $config['smime_encrypter']['cipher']);

        $config = $this->processSmimeEncrypter(['cipher' => \OPENSSL_CIPHER_RC2_40]);
        $this->assertSame(\OPENSSL_CIPHER_RC2_40, $config['smime_encrypter']['cipher']);
    }

    public function testSmimeEncrypterCipherDefaultsToNull()
    {
        $config = $this->processSmimeEncrypter([]);

        $this->assertNull($config['smime_encrypter']['cipher']);
    }

    public function testSmimeEncrypterCipherAsInvalidConstantName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"NOT_A_CIPHER" is not a valid OPENSSL cipher.');

        $this->processSmimeEncrypter(['cipher' => 'NOT_A_CIPHER']);
    }

    #[RequiresPhpExtension('openssl')]
    public function testSmimeEncrypterCipherAsInvalidConstantValue()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "mailer.smime_encrypter.cipher": You must provide a valid cipher.');

        $this->processSmimeEncrypter(['cipher' => 123456]);
    }

    public function testSmimeEncrypterCipherIsNotValidatedWithoutOpenssl()
    {
        if (\extension_loaded('openssl')) {
            $this->markTestSkipped('The "openssl" extension is loaded.');
        }

        $config = $this->processSmimeEncrypter(['cipher' => 123456]);
        $this->assertSame(123456, $config['smime_encrypter']['cipher']);

        $config = $this->processSmimeEncrypter([]);
        $this->assertNull($config['smime_encrypter']['cipher']);
    }

    private function processSmimeEncrypter(array $smimeEncrypter): array
    {
        return $this->process([
            'dsn' => 'null://null',
            'smime_encrypter' => $smimeEncrypter + ['repository' => 'my_certificate_repository'],
        ]);
    }

    private function process(array $config): array
    {
        return new Processor()->processConfiguration(new Configuration(new MailerBundle(), null, 'mailer'), [$config]);
    }
}
