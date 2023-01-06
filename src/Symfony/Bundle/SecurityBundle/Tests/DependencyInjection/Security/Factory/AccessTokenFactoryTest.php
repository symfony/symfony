<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Security\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AccessTokenFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AccessTokenFactoryTest extends TestCase
{
    public function testBasicServiceConfiguration()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => 'in_memory_token_handler_service_id',
            'success_handler' => 'success_handler_service_id',
            'failure_handler' => 'failure_handler_service_id',
            'token_extractors' => ['BAR', 'FOO'],
        ];

        $factory = new AccessTokenFactory();
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
    }

    public function testTokenExtractor()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => 'in_memory_token_handler_service_id',
            'success_handler' => 'success_handler_service_id',
            'failure_handler' => 'failure_handler_service_id',
            'token_extractors' => [
                'query_string' => [
                    'parameter' => 'c-auth-token',
                ]
            ],
        ];

        $factory = new AccessTokenFactory();
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
    }

    public function testDefaultServiceConfiguration()
    {
        $container = new ContainerBuilder();
        $config = [
            'token_handler' => 'in_memory_token_handler_service_id',
        ];

        $factory = new AccessTokenFactory();
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
        $this->assertEquals(
            'security.access_token_extractor.header',
            (string) $container->getDefinition('security.authenticator.access_token.firewall1')->getArgument(1)
        );
    }

    public function testNoExtractorsDefined()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "access_token.token_extractors" should have at least 1 element(s) defined.');
        $config = [
            'token_handler' => 'in_memory_token_handler_service_id',
            'success_handler' => 'success_handler_service_id',
            'failure_handler' => 'failure_handler_service_id',
            'token_extractors' => [],
        ];

        $factory = new AccessTokenFactory();
        $this->processConfig($config, $factory);
    }

    public function extractorConfigs(): array
    {
        return [
            'user_service_foo' => [
                ['foo'], // param,
                'foo',   // extractor(s)
                null,    // error
            ],

            'predefined query_string' => [
                ['query_string' => null],
                'security.access_token_extractor.query_string',
            ],

            'predefined header' => [
                ['header'],
                'security.access_token_extractor.header',
            ],

            'predefined query_string, not exists param' => [
                ['query_string' => ['tokenType' => 'this parameter does not exists in QueryStringExtractor']],
                'security.access_token_extractor.query_string',
            ],

            'user defined query_string' => [
                ['query_string' => ['parameter' => 'x-auth']],
                'security.authenticator.access_token.query_string_extractor.firewall1.0',
            ],

            '5 different services' => [
                [
                    'query_string' => null,
                    4 => 'request_body',
                    'header' => ['headerParameter' => 'x-auth-token'],
                    'header_x_bearer' => ['service' => 'header', 'tokenType' => 'X-Bearer'],
                    'foo' => null,
                    9 => ['service' => 'bar'],
                ],
                [
                    'security.access_token_extractor.query_string',
                    'security.access_token_extractor.request_body',
                    'security.authenticator.access_token.header_extractor.firewall1.0',
                    'security.authenticator.access_token.header_extractor.firewall1.1',
                    'foo',
                    'bar',
                ],
            ],

            'error: not exists param' => [
                ['header' => ['not_exists_param' => 'no matter']],
                null,
                'Unrecognized option "not_exists_param" under "access_token.token_extractors.header".',
            ],

            'error: undefined key' => [
                ['header' => ['not_exists_param' => 'no matter']],
                null,
                'Unrecognized option "not_exists_param" under "access_token.token_extractors.header".',
            ],

            'error: extractor misconfigured' => [
                ['abc' => 'abc'],
                null,
                'Please define extractor as "service_id" string or ["header|query_string|request_body" => ["abc" => "abc", ...].',
            ],
        ];
    }

    /**
     * @dataProvider extractorConfigs
     */
    public function testExtractorConfigs(array $extractorConfig, null|string|array $extractorIds, string $error = null): void
    {
        if ($error) {
            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessage($error);
        }

        $container = new ContainerBuilder();
        $config = [
            'token_handler' => 'in_memory_token_handler_service_id',
            'token_extractors' => $extractorConfig,
        ];

        $factory = new AccessTokenFactory();
        $finalizedConfig = $this->processConfig($config, $factory);

        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        if (!$error) {
            $this->assertTrue($container->hasDefinition('security.authenticator.access_token.firewall1'));
            $extractorDefinition = $container->getDefinition('security.authenticator.access_token.firewall1');

            if (is_string($extractorIds)) {
                $this->assertEquals(
                    $extractorIds,
                    (string) $extractorDefinition->getArgument(1)
                );
            } else { // all are in chain_extractor
                $this->assertEquals(
                    'security.authenticator.access_token.chain_extractor.firewall1',
                    (string) $extractorDefinition->getArgument(1)
                );
                $chainExtractor = $container->getDefinition($extractorDefinition->getArgument(1));
                $chainedExtractors = array_values($chainExtractor->getArgument(0));
                // order must be guaranteed
                $this->assertEquals($chainedExtractors, array_map('strval', $chainedExtractors));
            }
        }
    }

    public function testNoHandlerDefined()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "token_handler" under "access_token" must be configured.');
        $config = [
            'success_handler' => 'success_handler_service_id',
            'failure_handler' => 'failure_handler_service_id',
        ];

        $factory = new AccessTokenFactory();
        $this->processConfig($config, $factory);
    }

    private function processConfig(array $config, AccessTokenFactory $factory)
    {
        $nodeDefinition = new ArrayNodeDefinition('access_token');
        $factory->addConfiguration($nodeDefinition);

        $node = $nodeDefinition->getNode();
        $normalizedConfig = $node->normalize($config);

        return $node->finalize($normalizedConfig);
    }
}
