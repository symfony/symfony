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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration;
use Symfony\Bundle\FullStack;
use Symfony\Component\AssetMapper\Compressor\CompressorInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\JsonStreamer\JsonStreamWriter;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\Scheduler\Messenger\SchedulerTransportFactory;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Webhook\Controller\WebhookController;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [[
            'secret' => 's3cr3t',
            'serializer' => ['default_context' => ['foo' => 'bar']],
        ]]);

        $this->assertEquals(self::getBundleDefaultConfig(), $config);
    }

    public function testRateLimiterBuilderIsNotReadAsALimiterName()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [[
            // no "limiters" key: the shorthand below would otherwise read "builder" as a limiter name
            'rate_limiter' => [
                'builder' => ['cache_pool' => 'my.pool'],
            ],
        ]]);

        $this->assertSame([], $config['rate_limiter']['limiters']);
        $this->assertSame('my.pool', $config['rate_limiter']['builder']['cache_pool']);
    }

    public function testRateLimiterBuilderCanBeConfiguredAlongsideLimiters()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [[
            'rate_limiter' => [
                'limiters' => ['foo' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute']],
                'builder' => ['cache_pool' => 'my.pool'],
            ],
        ]]);

        $this->assertSame(['foo'], array_keys($config['rate_limiter']['limiters']));
        $this->assertSame('my.pool', $config['rate_limiter']['builder']['cache_pool']);
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

    public function testAssetsCanBeEnabled()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [[
            'assets' => null,
        ]]);

        $defaultConfig = [
            'enabled' => true,
            'version_strategy' => null,
            'version' => null,
            'version_format' => '%%s?%%s',
            'base_path' => '',
            'base_urls' => [],
            'packages' => [],
            'json_manifest_path' => null,
            'strict_mode' => false,
        ];

        $this->assertEquals($defaultConfig, $config['assets']);
    }

    public function testAssetMapperCanBeEnabled()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [[
            'asset_mapper' => null,
        ]]);

        $defaultConfig = [
            'enabled' => true,
            'paths' => [],
            'excluded_patterns' => [],
            'server' => true,
            'public_prefix' => '/assets/',
            'missing_import_mode' => 'warn',
            'extensions' => [],
            'importmap_path' => '%kernel.project_dir%/importmap.php',
            'importmap_polyfill' => 'es-module-shims',
            'importmap_entries' => 'all',
            'vendor_dir' => '%kernel.project_dir%/assets/vendor',
            'minimum_release_age' => 0,
            'importmap_script_attributes' => [],
            'importmap_integrity_algorithms' => [],
            'exclude_dotfiles' => true,
            'precompress' => [
                'enabled' => false,
                'formats' => [],
                'extensions' => CompressorInterface::DEFAULT_EXTENSIONS,
            ],
        ];

        $this->assertEquals($defaultConfig, $config['asset_mapper']);
    }

    #[DataProvider('provideImportmapPolyfillTests')]
    public function testAssetMapperPolyfillValue(mixed $polyfillValue, bool $isValid, mixed $expected)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        if (!$isValid) {
            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessage($expected);
        }

        $config = $processor->processConfiguration($configuration, [[
            'asset_mapper' => null === $polyfillValue ? [] : [
                'importmap_polyfill' => $polyfillValue,
            ],
        ]]);

        if ($isValid) {
            $this->assertEquals($expected, $config['asset_mapper']['importmap_polyfill']);
        }
    }

    public function testAssetMapperImportmapIntegrityAlgorithms()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $config = $processor->processConfiguration($configuration, [[
            'asset_mapper' => [
                'importmap_integrity_algorithms' => ['sha384'],
            ],
        ]]);

        $this->assertSame(['sha384'], $config['asset_mapper']['importmap_integrity_algorithms']);
    }

    public static function provideImportmapPolyfillTests()
    {
        yield [true, false, 'Must be either an importmap name or false.'];
        yield [null, true, 'es-module-shims'];
        yield ['es-module-shims', true, 'es-module-shims'];
        yield ['foo', true, 'foo'];
        yield [false, true, false];
    }

    #[DataProvider('provideValidAssetsPackageNameConfigurationTests')]
    public function testValidAssetsPackageNameConfiguration($packageName)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [
            [
                'assets' => [
                    'packages' => [
                        $packageName => [],
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey($packageName, $config['assets']['packages']);
    }

    public static function provideValidAssetsPackageNameConfigurationTests(): array
    {
        return [
            ['foobar'],
            ['foo-bar'],
            ['foo_bar'],
        ];
    }

    #[DataProvider('provideInvalidAssetConfigurationTests')]
    public function testInvalidAssetsConfiguration(array $assetConfig, $expectedMessage)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $processor->processConfiguration($configuration, [
            [
                'assets' => $assetConfig,
            ],
        ]);
    }

    public static function provideInvalidAssetConfigurationTests(): iterable
    {
        // helper to turn config into embedded package config
        $createPackageConfig = static fn (array $packageConfig) => [
            'base_urls' => '//example.com',
            'version' => 1,
            'packages' => [
                'foo' => $packageConfig,
            ],
        ];

        $config = [
            'version' => 1,
            'version_strategy' => 'foo',
        ];
        yield [$config, 'You cannot use both "version_strategy" and "version" at the same time under "assets".'];
        yield [$createPackageConfig($config), 'You cannot use both "version_strategy" and "version" at the same time under "assets" packages.'];

        $config = [
            'json_manifest_path' => '/foo.json',
            'version_strategy' => 'foo',
        ];
        yield [$config, 'You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets".'];
        yield [$createPackageConfig($config), 'You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets" packages.'];

        $config = [
            'json_manifest_path' => '/foo.json',
            'version' => '1',
        ];
        yield [$config, 'You cannot use both "version" and "json_manifest_path" at the same time under "assets".'];
        yield [$createPackageConfig($config), 'You cannot use both "version" and "json_manifest_path" at the same time under "assets" packages.'];
    }

    public function testSerializerJsonDetailedErrorMessagesEnabledWhenDefaultContextIsConfigured()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [[
            'serializer' => [
                'default_context' => [
                    'foo' => 'bar',
                ],
            ],
        ]]);

        $this->assertSame(['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => true], $config['serializer']['default_context'] ?? []);
    }

    public function testSerializerJsonDetailedErrorMessagesInDefaultContextCanBeDisabled()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [[
            'serializer' => [
                'default_context' => [
                    'foo' => 'bar',
                    JsonDecode::DETAILED_ERROR_MESSAGES => false,
                ],
            ],
        ]]);

        $this->assertSame(['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => false], $config['serializer']['default_context'] ?? []);
    }

    public function testSerializerJsonDetailedErrorMessagesInDefaultContextCanBeDisabledWithSeveralConfigsBeingMerged()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [
            [
                'serializer' => [
                    'default_context' => [
                        'foo' => 'bar',
                        JsonDecode::DETAILED_ERROR_MESSAGES => false,
                    ],
                ],
            ],
            [
                'serializer' => [
                    'default_context' => [
                        'foobar' => 'baz',
                    ],
                ],
            ],
        ]);

        $this->assertSame(['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => false, 'foobar' => 'baz'], $config['serializer']['default_context'] ?? []);
    }

    public function testScopedHttpClientsInheritRateLimiterAndRetryFailedConfiguration()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $config = $processor->processConfiguration($configuration, [[
            'http_client' => [
                'default_options' => ['rate_limiter' => 'default_limiter', 'retry_failed' => ['max_retries' => 77]],
                'scoped_clients' => [
                    'foo' => ['base_uri' => 'http://example.com'],
                    'bar' => ['base_uri' => 'http://example.com', 'rate_limiter' => true, 'retry_failed' => true],
                    'baz' => ['base_uri' => 'http://example.com', 'rate_limiter' => false, 'retry_failed' => false],
                    'qux' => ['base_uri' => 'http://example.com', 'rate_limiter' => 'foo_limiter', 'retry_failed' => ['max_retries' => 88, 'delay' => 999]],
                ],
            ],
        ]]);

        $scopedClients = $config['http_client']['scoped_clients'];

        $this->assertSame('default_limiter', $scopedClients['foo']['rate_limiter']);
        $this->assertTrue($scopedClients['foo']['retry_failed']['enabled']);
        $this->assertSame(77, $scopedClients['foo']['retry_failed']['max_retries']);
        $this->assertSame(1000, $scopedClients['foo']['retry_failed']['delay']);

        $this->assertSame('default_limiter', $scopedClients['bar']['rate_limiter']);
        $this->assertTrue($scopedClients['bar']['retry_failed']['enabled']);
        $this->assertSame(77, $scopedClients['bar']['retry_failed']['max_retries']);
        $this->assertSame(1000, $scopedClients['bar']['retry_failed']['delay']);

        $this->assertNull($scopedClients['baz']['rate_limiter']);
        $this->assertFalse($scopedClients['baz']['retry_failed']['enabled']);
        $this->assertSame(3, $scopedClients['baz']['retry_failed']['max_retries']);
        $this->assertSame(1000, $scopedClients['baz']['retry_failed']['delay']);

        $this->assertSame('foo_limiter', $scopedClients['qux']['rate_limiter']);
        $this->assertTrue($scopedClients['qux']['retry_failed']['enabled']);
        $this->assertSame(88, $scopedClients['qux']['retry_failed']['max_retries']);
        $this->assertSame(999, $scopedClients['qux']['retry_failed']['delay']);
    }

    public function testSerializerJsonDetailedErrorMessagesEnabledByDefaultWithDebugEnabled()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [
            [
                'serializer' => null,
            ],
        ]);

        $this->assertSame([JsonDecode::DETAILED_ERROR_MESSAGES => true], $config['serializer']['default_context'] ?? []);
    }

    public function testSerializerJsonDetailedErrorMessagesNotSetByDefaultWithDebugDisabled()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(false), [
            [
                'serializer' => null,
            ],
        ]);

        $this->assertSame([], $config['serializer']['default_context'] ?? []);
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

    #[RequiresPhpExtension('openssl')]
    public function testMailerSmimeEncrypterCipherAsConstantName()
    {
        $config = self::processSmimeEncrypterConfig(['cipher' => 'AES_256_CBC']);
        $this->assertSame(\OPENSSL_CIPHER_AES_256_CBC, $config['mailer']['smime_encrypter']['cipher']);

        $config = self::processSmimeEncrypterConfig(['cipher' => 'RC2_40']);
        $this->assertSame(\OPENSSL_CIPHER_RC2_40, $config['mailer']['smime_encrypter']['cipher']);
    }

    #[RequiresPhpExtension('openssl')]
    public function testMailerSmimeEncrypterCipherAsConstantValue()
    {
        $config = self::processSmimeEncrypterConfig(['cipher' => \OPENSSL_CIPHER_AES_256_CBC]);
        $this->assertSame(\OPENSSL_CIPHER_AES_256_CBC, $config['mailer']['smime_encrypter']['cipher']);

        $config = self::processSmimeEncrypterConfig(['cipher' => \OPENSSL_CIPHER_RC2_40]);
        $this->assertSame(\OPENSSL_CIPHER_RC2_40, $config['mailer']['smime_encrypter']['cipher']);
    }

    public function testMailerSmimeEncrypterCipherDefaultsToNull()
    {
        $config = self::processSmimeEncrypterConfig([]);

        $this->assertNull($config['mailer']['smime_encrypter']['cipher']);
    }

    public function testMailerSmimeEncrypterCipherAsInvalidConstantName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"NOT_A_CIPHER" is not a valid OPENSSL cipher.');

        self::processSmimeEncrypterConfig(['cipher' => 'NOT_A_CIPHER']);
    }

    #[RequiresPhpExtension('openssl')]
    public function testMailerSmimeEncrypterCipherAsInvalidConstantValue()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "framework.mailer.smime_encrypter.cipher": You must provide a valid cipher.');

        self::processSmimeEncrypterConfig(['cipher' => 123456]);
    }

    public function testMailerSmimeEncrypterCipherIsNotValidatedWithoutOpenssl()
    {
        if (\extension_loaded('openssl')) {
            $this->markTestSkipped('The "openssl" extension is loaded.');
        }

        $config = self::processSmimeEncrypterConfig(['cipher' => 123456]);
        $this->assertSame(123456, $config['mailer']['smime_encrypter']['cipher']);

        $config = self::processSmimeEncrypterConfig([]);
        $this->assertNull($config['mailer']['smime_encrypter']['cipher']);
    }

    private static function processSmimeEncrypterConfig(array $smimeEncrypter): array
    {
        return (new Processor())->processConfiguration(new Configuration(true), [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'mailer' => [
                    'dsn' => 'null://null',
                    'smime_encrypter' => $smimeEncrypter + ['repository' => 'my_certificate_repository'],
                ],
            ],
        ]);
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
            'serializer' => [
                'default_context' => ['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => true],
                'enabled' => true,
                'enable_attributes' => !class_exists(FullStack::class),
                'mapping' => ['paths' => []],
                'named_serializers' => [],
            ],
            'property_info' => [
                'enabled' => !class_exists(FullStack::class),
                'with_constructor_extractor' => true,
            ],
            'router' => [
                'enabled' => false,
                'default_uri' => null,
                'http_port' => 80,
                'https_port' => 443,
                'strict_requirements' => true,
                'utf8' => true,
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
            'assets' => [
                'enabled' => !class_exists(FullStack::class),
                'version_strategy' => null,
                'version' => null,
                'version_format' => '%%s?%%s',
                'base_path' => '',
                'base_urls' => [],
                'packages' => [],
                'json_manifest_path' => null,
                'strict_mode' => false,
            ],
            'asset_mapper' => [
                'enabled' => !class_exists(FullStack::class),
                'paths' => [],
                'excluded_patterns' => [],
                'server' => true,
                'public_prefix' => '/assets/',
                'missing_import_mode' => 'warn',
                'extensions' => [],
                'importmap_path' => '%kernel.project_dir%/importmap.php',
                'importmap_polyfill' => 'es-module-shims',
                'importmap_entries' => 'all',
                'vendor_dir' => '%kernel.project_dir%/assets/vendor',
                'minimum_release_age' => 0,
                'importmap_script_attributes' => [],
                'importmap_integrity_algorithms' => [],
                'exclude_dotfiles' => true,
                'precompress' => [
                    'enabled' => false,
                    'formats' => [],
                    'extensions' => CompressorInterface::DEFAULT_EXTENSIONS,
                ],
            ],
            'php_errors' => [
                'log' => true,
                'throw' => true,
            ],
            'disallow_search_engine_index' => true,
            'http_client' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(HttpClient::class),
                'scoped_clients' => [],
            ],
            'mailer' => [
                'dsn' => null,
                'transports' => [],
                'enabled' => !class_exists(FullStack::class) && class_exists(Mailer::class),
                'message_bus' => null,
                'headers' => [],
                'tracking' => [
                    'opens' => null,
                    'clicks' => null,
                ],
                'dkim_signer' => [
                    'enabled' => false,
                    'options' => [],
                    'key' => '',
                    'domain' => '',
                    'select' => '',
                    'passphrase' => '',
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
            ],
            'notifier' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(Notifier::class),
                'message_bus' => null,
                'chatter_transports' => [],
                'texter_transports' => [],
                'channel_policy' => [],
                'admin_recipients' => [],
                'notification_on_failed_messages' => false,
            ],
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
            'rate_limiter' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(TokenBucketLimiter::class),
                'limiters' => [],
                'builder' => [
                    'lock_factory' => 'auto',
                    'cache_pool' => 'cache.rate_limiter',
                    'storage_service' => null,
                ],
            ],
            'scheduler' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(SchedulerTransportFactory::class),
            ],
            'exceptions' => [],
            'webhook' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(WebhookController::class),
                'routing' => [],
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
            ],
            'json_streamer' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(JsonStreamWriter::class),
                'default_options' => [
                    'include_null_properties' => false,
                ],
            ],
        ];
    }

    public function testNamedSerializersReservedName()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "framework.serializer.named_serializers": "default" is a reserved name.');

        $processor->processConfiguration($configuration, [[
            'serializer' => [
                'named_serializers' => [
                    'default' => ['include_built_in_normalizers' => false],
                ],
            ],
        ]]);
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
