<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AzureKeyVault\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\AzureKeyVault;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\AzureKeyVaultFactory;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\ClientCredentialsTokenProvider;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;

class AzureKeyVaultFactoryTest extends TestCase
{
    public function testSupportsTheAzureScheme()
    {
        $this->assertTrue((new AzureKeyVaultFactory())->supports(Dsn::fromString('azure-keyvault://id:secret@my-vault.vault.azure.net?tenant=t')));
    }

    public function testRejectsForeignSchemes()
    {
        $this->assertFalse((new AzureKeyVaultFactory())->supports(Dsn::fromString('sodium://?keys[a]=AAAA')));
    }

    public function testCreateOnUnsupportedSchemeThrows()
    {
        $this->expectException(UnsupportedSchemeException::class);
        (new AzureKeyVaultFactory())->create(Dsn::fromString('sodium://?keys[a]=AAAA'));
    }

    public function testCreateBuildsAzureKeyVault()
    {
        $kms = (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id:secret@my-vault.vault.azure.net?tenant=t'));

        $this->assertInstanceOf(AzureKeyVault::class, $kms);
    }

    public function testCreateAcceptsCustomAlgorithmAndApiVersion()
    {
        $kms = (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id:secret@v.managedhsm.azure.net?tenant=t&algorithm=A256GCM&wrap_algorithm=A256KW&api_version=7.5'));

        $this->assertInstanceOf(AzureKeyVault::class, $kms);
    }

    public function testHostIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('vault host');
        (new AzureKeyVaultFactory())->create(new Dsn(scheme: 'azure-keyvault', user: 'id', password: 'secret', options: ['tenant' => 't']));
    }

    public function testClientIdIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('client id');
        (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://my-vault.vault.azure.net?tenant=t'));
    }

    public function testClientSecretIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('client secret');
        (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id@my-vault.vault.azure.net?tenant=t'));
    }

    public function testTenantIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tenant');
        (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id:secret@my-vault.vault.azure.net'));
    }

    public function testUnknownDsnOptionIsRejected()
    {
        // A near miss like "algorithms" must not silently keep the default algorithm.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option "algorithms"');
        (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id:secret@my-vault.vault.azure.net?tenant=t&algorithms=A256GCM'));
    }

    public function testArrayDsnOptionIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"tenant" option');
        (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id:secret@my-vault.vault.azure.net?tenant[]=t'));
    }

    public function testStandardVaultHostUsesTheVaultAudience()
    {
        $kms = (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id:secret@my-vault.vault.azure.net?tenant=t'));

        $this->assertSame('https://vault.azure.net/.default', self::audienceOf($kms));
    }

    public function testManagedHsmHostUsesTheManagedHsmAudience()
    {
        $kms = (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id:secret@my-hsm.managedhsm.azure.net?tenant=t'));

        $this->assertSame('https://managedhsm.azure.net/.default', self::audienceOf($kms));
    }

    public function testLookAlikeHostDoesNotMatchManagedHsm()
    {
        // "managedhsm" appears as a substring but is not the suffix; the previous
        // str_contains() heuristic would incorrectly treat this as Managed HSM.
        $kms = (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id:secret@managedhsm-fake.example.com?tenant=t'));

        $this->assertSame('https://vault.azure.net/.default', self::audienceOf($kms));
    }

    public function testAudienceOptionOverridesTheHostHeuristic()
    {
        $kms = (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id:secret@my-hsm.managedhsm.azure.net?tenant=t&audience=https%3A%2F%2Fvault.usgovcloudapi.net%2F.default'));

        $this->assertSame('https://vault.usgovcloudapi.net/.default', self::audienceOf($kms));
    }

    private static function audienceOf(AzureKeyVault $kms): string
    {
        $tokensProperty = (new \ReflectionClass(AzureKeyVault::class))->getProperty('tokens');
        $tokens = $tokensProperty->getValue($kms);
        $audienceProperty = (new \ReflectionClass(ClientCredentialsTokenProvider::class))->getProperty('audience');

        return $audienceProperty->getValue($tokens);
    }
}
