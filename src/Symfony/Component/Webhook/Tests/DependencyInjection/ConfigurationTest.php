<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Webhook\WebhookBundle;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $this->assertSame([
            'enabled' => true,
            'message_bus' => 'messenger.default_bus',
            'http_client' => 'http_client',
            'no_private_network' => [
                'enabled' => false,
                'subnets' => null,
                'allow_list' => [],
            ],
            'event_header_name' => 'Webhook-Event',
            'id_header_name' => 'Webhook-Id',
            'timestamp_header_name' => 'Webhook-Timestamp',
            'signature_header_name' => 'Webhook-Signature',
            'signing_algorithm' => 'sha256',
            'signature_format' => 'legacy',
            'timestamp_tolerance' => 300,
            'routing' => [],
        ], $this->process([]));
    }

    public function testRoutingKeysAreNotNormalized()
    {
        $config = $this->process([
            'routing' => [
                'mailer-provider' => ['service' => 'mailer.webhook.request_parser.mailgun', 'secret' => 'the-secret'],
            ],
        ]);

        $this->assertSame(['mailer-provider' => ['service' => 'mailer.webhook.request_parser.mailgun', 'secret' => 'the-secret']], $config['routing']);
    }

    public function testTheSecretDefaultsToAnEmptyString()
    {
        $config = $this->process(['routing' => ['test' => ['service' => 'parser']]]);

        $this->assertSame('', $config['routing']['test']['secret']);
    }

    public function testASingleSubnetCanBeGivenAsAString()
    {
        $config = $this->process(['no_private_network' => ['subnets' => '10.0.0.0/8', 'allow_list' => '10.1.2.3']]);

        $this->assertSame(['10.0.0.0/8'], $config['no_private_network']['subnets']);
        $this->assertSame(['10.1.2.3'], $config['no_private_network']['allow_list']);
        $this->assertTrue($config['no_private_network']['enabled']);
    }

    public function testUnknownSignatureFormatsAreRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "v2" is not allowed for path "webhook.signature_format".');

        $this->process(['signature_format' => 'v2']);
    }

    public function testNegativeTimestampTolerancesAreRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value -1 is too small for path "webhook.timestamp_tolerance". Should be greater than or equal to 0');

        $this->process(['timestamp_tolerance' => -1]);
    }

    private function process(array $config): array
    {
        return new Processor()->processConfiguration(new Configuration(new WebhookBundle(), null, 'webhook'), [$config]);
    }
}
