<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration;
use Symfony\Bundle\FullStack;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [[
            'secret' => 's3cr3t',
        ]]);

        $this->assertEquals(self::getBundleDefaultConfig(), $config);
    }

    public function testTranslatorProviderDomainsCanBeKeyed()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [[
            'translator' => [
                'providers' => [
                    'loco' => [
                        'dsn' => 'loco://API_KEY@default',
                        // as an XML configuration is converted
                        'domains' => [
                            ['key' => 'foo', 'value' => 'bar'],
                            ['key' => '', 'value' => '*'],
                        ],
                    ],
                ],
            ],
        ]]);

        $this->assertSame(['foo' => 'bar', '' => '*'], $config['translator']['providers']['loco']['domains']);
    }

    public function getTestValidSessionName()
    {
        return [
            [null],
            ['PHPSESSID'],
            ['a&b'],
            [',_-!@#$%^*(){}:<>/?'],
        ];
    }

    #[DataProvider('provideEquivalentProfilerExclusions')]
    public function testProfilerExcludedHttpCodesAreNormalized(array $excludedHttpCodes)
    {
        $config = (new Processor())->processConfiguration(new Configuration(true), [[
            'profiler' => ['excluded_http_codes' => $excludedHttpCodes],
        ]]);

        $this->assertSame([
            404 => [],
            400 => ['^/foo', '^/bar'],
        ], $config['profiler']['excluded_http_codes']);
    }

    public static function provideEquivalentProfilerExclusions(): iterable
    {
        yield 'mapping' => [[404 => null, 400 => ['^/foo', '^/bar']]];
        yield 'mapping with an explicit empty list' => [[404 => [], 400 => ['^/foo', '^/bar']]];
        yield 'mapping with true' => [[404 => true, 400 => ['^/foo', '^/bar']]];
        yield 'mixed forms' => [[404, 400 => ['^/foo', '^/bar']]];
    }

    public function testProfilerExcludedHttpCodesAcceptsABareStatusCode()
    {
        $config = (new Processor())->processConfiguration(new Configuration(true), [[
            'profiler' => ['excluded_http_codes' => 404],
        ]]);

        $this->assertSame([404 => []], $config['profiler']['excluded_http_codes']);
    }

    public function testProfilerExcludedHttpCodesAcceptsASinglePathAsAString()
    {
        $config = (new Processor())->processConfiguration(new Configuration(true), [[
            'profiler' => ['excluded_http_codes' => [404 => '^/foo']],
        ]]);

        $this->assertSame([404 => ['^/foo']], $config['profiler']['excluded_http_codes']);
    }

    public function testProfilerExcludedHttpCodesSkipsCodesDisabledWithFalse()
    {
        $config = (new Processor())->processConfiguration(new Configuration(true), [[
            'profiler' => ['excluded_http_codes' => [404 => false, 400 => null]],
        ]]);

        $this->assertSame([400 => []], $config['profiler']['excluded_http_codes']);
    }

    public function testProfilerExcludedHttpCodesAreOverriddenAcrossFiles()
    {
        $config = (new Processor())->processConfiguration(new Configuration(true), [
            ['profiler' => ['excluded_http_codes' => [404]]],
            ['profiler' => ['excluded_http_codes' => [404 => ['^/api']]]],
        ]);

        $this->assertSame([404 => ['^/api']], $config['profiler']['excluded_http_codes']);
    }

    public function testProfilerExcludedPathsAcceptsABareRegularExpression()
    {
        $config = (new Processor())->processConfiguration(new Configuration(true), [[
            'profiler' => ['excluded_paths' => '^/\.well-known/'],
        ]]);

        $this->assertSame(['^/\.well-known/'], $config['profiler']['excluded_paths']);
    }

    #[DataProvider('provideOutOfRangeHttpCodes')]
    public function testProfilerExcludedHttpCodesRejectsCodesOutOfRange(int $code, string $message)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        (new Processor())->processConfiguration(new Configuration(true), [[
            'profiler' => ['excluded_http_codes' => [$code]],
        ]]);
    }

    public static function provideOutOfRangeHttpCodes(): iterable
    {
        yield 'below the lower bound' => [99, 'only accepts HTTP status codes between 100 and 599, "99" given'];
        yield 'above the upper bound' => [600, 'only accepts HTTP status codes between 100 and 599, "600" given'];
        yield 'negative' => [-1, 'only accepts HTTP status codes between 100 and 599, "-1" given'];
    }

    public function testProfilerExcludedPathsRejectsEmptyPatterns()
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(true), [[
            'profiler' => ['excluded_paths' => ['']],
        ]]);
    }

    public function testProfilerExcludedHttpCodesRejectsEmptyPathPatterns()
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(true), [[
            'profiler' => ['excluded_http_codes' => [404 => ['']]],
        ]]);
    }

    public function testProfilerExcludedPathsRejectsInvalidRegularExpressions()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid regular expression in the "excluded_paths" option');

        (new Processor())->processConfiguration(new Configuration(true), [[
            'profiler' => ['excluded_paths' => ['^/foo(']],
        ]]);
    }

    public function testProfilerExcludedHttpCodesRejectsInvalidRegularExpressions()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid regular expression in the "excluded_http_codes" option');

        (new Processor())->processConfiguration(new Configuration(true), [[
            'profiler' => ['excluded_http_codes' => [404 => ['^/foo(']]],
        ]]);
    }

    #[DataProvider('getTestInvalidSessionName')]
    public function testInvalidSessionName($sessionName)
    {
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);

        $processor->processConfiguration(
            new Configuration(true),
            [[
                'session' => ['name' => $sessionName, 'cookie_secure' => 'auto', 'cookie_samesite' => 'lax'],
            ]]
        );
    }

    public static function getTestInvalidSessionName()
    {
        return [
            ['a.b'],
            ['a['],
            ['a[]'],
            ['a[b]'],
            ['a=b'],
            ['a+b'],
        ];
    }

    public function testFormCsrfProtectionFieldAttrDoNotNormalizeKeys()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(false), [
            [
                'form' => [
                    'csrf_protection' => [
                        'field_attr' => ['data-example-attr' => 'value'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(['data-example-attr' => 'value'], $config['form']['csrf_protection']['field_attr'] ?? []);
    }

    #[TestWith(['CONNECT'])]
    #[TestWith(['GET'])]
    #[TestWith(['HEAD'])]
    #[TestWith(['TRACE'])]
    public function testInvalidHttpMethodOverride(string $method)
    {
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The HTTP methods "GET", "HEAD", "CONNECT", and "TRACE" cannot be overridden.');

        $processor->processConfiguration(
            new Configuration(true),
            [[
                'allowed_http_method_override' => [$method],
            ]]
        );
    }

    public function testRemoteEventCanBeConfigured()
    {
        $processor = new Processor();

        foreach (['remote_event', 'remote-event'] as $key) {
            foreach ([true, false] as $enabled) {
                $config = $processor->processConfiguration(new Configuration(true), [
                    [
                        'http_method_override' => false,
                        'handle_all_throwables' => true,
                        'php_errors' => ['log' => true],
                        $key => ['enabled' => $enabled],
                    ],
                ]);

                $this->assertSame(['enabled' => $enabled], $config['remote_event'], $key);
            }
        }
    }

    protected static function getBundleDefaultConfig()
    {
        return [
            'http_method_override' => false,
            'allowed_http_method_override' => null,
            'handle_all_throwables' => true,
            'trust_x_sendfile_type_header' => '%env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)%',
            'ide' => '%env(default::SYMFONY_IDE)%',
            'default_locale' => 'en',
            'enabled_locales' => [],
            'set_locale_from_accept_language' => false,
            'set_content_language_from_locale' => false,
            'secret' => 's3cr3t',
            'trusted_hosts' => ['%env(default::SYMFONY_TRUSTED_HOSTS)%'],
            'trusted_proxies' => ['%env(default::SYMFONY_TRUSTED_PROXIES)%'],
            'trusted_headers' => ['%env(default::SYMFONY_TRUSTED_HEADERS)%'],
            'csrf_protection' => [
                'enabled' => null,
                'cookie_name' => 'csrf-token',
                'check_header' => false,
                'stateless_token_ids' => [],
            ],
            'form' => [
                'enabled' => !class_exists(FullStack::class),
                'csrf_protection' => [
                    'enabled' => null, // defaults to csrf_protection.enabled
                    'field_name' => '_token',
                    'field_attr' => ['data-controller' => 'csrf-protection'],
                    'token_id' => null,
                ],
            ],
            'esi' => ['enabled' => false],
            'ssi' => ['enabled' => false],
            'fragments' => [
                'enabled' => false,
                'path' => '/_fragment',
                'hinclude_default_template' => null,
            ],
            'uri_signer' => [
                'expiration' => null,
            ],
            'profiler' => [
                'enabled' => false,
                'only_exceptions' => false,
                'only_main_requests' => false,
                'excluded_paths' => [],
                'excluded_http_codes' => [],
                'dsn' => 'file:%kernel.cache_dir%/profiler',
                'collect' => true,
                'collect_parameter' => null,
                'collect_serializer_data' => true,
            ],
            'translator' => [
                'enabled' => !class_exists(FullStack::class),
                'fallbacks' => [],
                'cache_dir' => '%kernel.cache_dir%/translations',
                'logging' => false,
                'formatter' => 'translator.formatter.default',
                'paths' => [],
                'default_path' => '%kernel.project_dir%/translations',
                'pseudo_localization' => [
                    'enabled' => false,
                    'accents' => true,
                    'expansion_factor' => 1.0,
                    'brackets' => true,
                    'parse_html' => false,
                    'localizable_html_attributes' => [],
                ],
                'providers' => [],
                'globals' => [],
            ],
            'validation' => [
                'enabled' => !class_exists(FullStack::class),
                'enable_attributes' => !class_exists(FullStack::class),
                'static_method' => ['loadValidatorMetadata'],
                'translation_domain' => 'validators',
                'disable_translation' => false,
                'property_metadata_existence_check' => false,
                'mapping' => [
                    'paths' => [],
                ],
                'auto_mapping' => [],
                'not_compromised_password' => [
                    'enabled' => true,
                    'endpoint' => null,
                ],
                'email_validation_mode' => 'html5',
            ],
            'session' => [
                'enabled' => false,
                'storage_factory_id' => 'session.storage.factory.native',
                'cookie_httponly' => true,
                'cookie_samesite' => 'lax',
                'cookie_secure' => 'auto',
                'metadata_update_threshold' => 0,
            ],
            'request' => [
                'enabled' => false,
                'formats' => [],
            ],
            'php_errors' => [
                'log' => true,
                'throw' => true,
            ],
            'disallow_search_engine_index' => true,
            'error_controller' => 'error_controller',
            'secrets' => [
                'enabled' => true,
                'vault_directory' => '%kernel.project_dir%/config/secrets/%kernel.runtime_environment%',
                'local_dotenv_file' => '%kernel.project_dir%/.env.%kernel.environment%.local',
                'decryption_env_var' => 'base64:default::SYMFONY_DECRYPTION_SECRET',
            ],
            'http_cache' => [
                'enabled' => false,
                'debug' => '%kernel.debug%',
                'private_headers' => [],
                'skip_response_headers' => [],
            ],
            'exceptions' => [],
        ];
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testTerminateOnCacheHitDeprecation()
    {
        $this->expectUserDeprecationMessage('Since symfony/framework-bundle 8.1: Setting the "framework.http_cache.terminate_on_cache_hit" configuration option is deprecated. It will be removed in version 9.0.');

        $processor = new Processor();
        $processor->processConfiguration(new Configuration(true), [[
            'http_cache' => ['terminate_on_cache_hit' => true],
        ]]);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testIdeDeprecation()
    {
        $this->expectUserDeprecationMessage('Since symfony/framework-bundle 8.2: Setting the "framework.ide" configuration option is deprecated, use the "SYMFONY_IDE" env var instead.');

        $processor = new Processor();
        $processor->processConfiguration(new Configuration(true), [[
            'ide' => 'phpstorm',
        ]]);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testHincludeDefaultTemplateDeprecation()
    {
        $this->expectUserDeprecationMessage('Since symfony/framework-bundle 8.2: Setting the "framework.fragments.hinclude_default_template" configuration option is deprecated. It will be removed in version 9.0.');

        $processor = new Processor();
        $processor->processConfiguration(new Configuration(true), [[
            'fragments' => ['hinclude_default_template' => 'default.html.twig'],
        ]]);
    }
}
