<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayShapeGenerator;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Dumper\JsonSchemaDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\KeyManagement\KeyManagementBundle;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $this->assertSame([
            'enabled' => true,
            'default_client' => null,
            'clients' => [],
        ], $this->process([]));
    }

    public function testADsnAtTheRootIsTheDefaultClient()
    {
        $config = $this->process('sodium://?keys[app]=AAAA');

        $this->assertSame(['default' => ['dsn' => 'sodium://?keys[app]=AAAA', 'members' => []]], $config['clients']);
        $this->assertTrue($config['enabled']);
    }

    public function testADsnAsTheClientsIsTheDefaultClient()
    {
        $config = $this->process(['clients' => 'sodium://?keys[app]=AAAA', 'enabled' => false]);

        $this->assertSame(['default' => ['dsn' => 'sodium://?keys[app]=AAAA', 'members' => []]], $config['clients']);
        $this->assertFalse($config['enabled']);
    }

    public function testAClientCanBeTheMembersOfACompositeOne()
    {
        $config = $this->process(['clients' => [
            'aws' => 'aws-kms://default',
            'azure' => 'azure-keyvault://default',
            'main' => ['members' => ['aws' => null, 'azure' => 'https://vault.azure.net/keys/app']],
        ]]);

        $this->assertSame(['members' => ['aws' => null, 'azure' => 'https://vault.azure.net/keys/app']], $config['clients']['main']);
        $this->assertSame(['dsn' => 'aws-kms://default', 'members' => []], $config['clients']['aws']);
    }

    public function testAClientIsADsnOrMembersNotBoth()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('A KMS client is either a DSN or the members of a composite client, not both nor neither.');

        $this->process(['clients' => ['main' => ['dsn' => 'aws-kms://default', 'members' => ['aws' => null]]]]);
    }

    public function testAClientIsADsnOrMembersNotNeither()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('A KMS client is either a DSN or the members of a composite client, not both nor neither.');

        $this->process(['clients' => ['main' => ['members' => []]]]);
    }

    public function testACompositeClientCannotBeItsOwnMember()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The composite KMS client "main" cannot be a member of itself.');

        $this->process(['clients' => ['main' => ['members' => ['main' => null, 'aws' => null]]]]);
    }

    public function testAClientIsDocumentedAsADsnOrItsMembers()
    {
        $tree = new Configuration(new KeyManagementBundle(), null, 'key_management')->getConfigTreeBuilder()->buildTree();

        $client = new JsonSchemaDumper()->dump($tree)['anyOf'][0]['properties']['clients']['anyOf'][0]['additionalProperties'];
        $this->assertContains(['type' => ['string']], $client['anyOf'] ?? [], 'a client is a DSN string');

        $shape = ArrayShapeGenerator::generate($tree);
        $this->assertMatchesRegularExpression('/clients\?: [^\n]*array<string, [^\n]*string\|array\{/', $shape, 'or the map of its members');
        $this->assertStringNotContainsString('...<string, mixed>', $shape, 'and nothing else');
    }

    public function testClientNamesAreKeptAsTheyAre()
    {
        $config = $this->process(['clients' => ['my-kms' => 'sodium://?keys[app]=AAAA', 'Vault' => 'hashicorp-vault-transit://t@vault.local:8200/v1/']]);

        $this->assertSame(['my-kms', 'Vault'], array_keys($config['clients']));
    }

    public function testAClientNameMustBeAName()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The KMS client name "0" is invalid');

        $this->process(['clients' => ['sodium://?keys[app]=AAAA']]);
    }

    public function testADsnMustBeAString()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "key_management.clients.app.dsn": The DSN of a KMS client must be a string, got 5.');

        $this->process(['clients' => ['app' => ['dsn' => 5]]]);
    }

    public function testAnEmptyDsnIsRefused()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('key_management.clients.app');

        $this->process(['clients' => ['app' => '']]);
    }

    public function testTheStoreDefaults()
    {
        $config = $this->process(['store' => ['client' => 'app', 'key_id' => 'alias/app-key']]);

        $this->assertSame([
            'client' => 'app',
            'key_id' => 'alias/app-key',
            'connection' => 'doctrine.dbal.default_connection',
            'table' => 'key_management_data_keys',
            'max_age' => 2592000,
        ], $config['store']);
    }

    public function testTheStoreNeedsAKeyId()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "key_id" under "key_management.store" must be configured');

        $this->process(['store' => ['client' => 'app']]);
    }

    public function testTheStoreClientIsOptional()
    {
        $config = $this->process(['store' => ['key_id' => 'alias/app-key']]);

        $this->assertNull($config['store']['client']);
    }

    public function testANegativeMaxAgeIsRefused()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('key_management.store.max_age');

        $this->process(['store' => ['client' => 'app', 'key_id' => 'k', 'max_age' => -1]]);
    }

    private function process(mixed $config): array
    {
        return new Processor()->processConfiguration(new Configuration(new KeyManagementBundle(), null, 'key_management'), [$config]);
    }
}
