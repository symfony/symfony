<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass as ContainerRemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Validator\ValidationBundle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationBundleTest extends TestCase
{
    public function testValidationEnabledByDefault()
    {
        // the bundle is registered on its own now, so the validator is on unless it is turned off
        $this->assertTrue($this->load([])->has('validator'));
    }

    public function testValidationDisabled()
    {
        $container = $this->load(['enabled' => false]);

        $this->assertFalse($container->has('validator'));
        $this->assertFalse($container->has('cache.validator'));
    }

    public function testValidatorBuilderCalls()
    {
        $calls = $this->load([])->getDefinition('validator.builder')->getMethodCalls();

        $this->assertSame('setConstraintValidatorFactory', $calls[0][0]);
        $this->assertEquals([new Reference('validator.validator_factory')], $calls[0][1]);
        $this->assertSame('setGroupProviderLocator', $calls[1][0]);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $calls[1][1][0]);
        $this->assertSame('setTranslator', $calls[2][0]);
        $this->assertEquals([new Reference('translator', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE)], $calls[2][1]);
        $this->assertSame('setTranslationDomain', $calls[3][0]);
        $this->assertSame(['%validator.translation_domain%'], $calls[3][1]);
        $this->assertSame('enableAttributeMapping', $calls[4][0]);
        $this->assertSame('addMethodMapping', $calls[5][0]);
        $this->assertSame(['loadValidatorMetadata'], $calls[5][1]);
        $this->assertSame('setMappingCache', $calls[6][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[6][1]);
        $this->assertCount(7, $calls);
    }

    public function testAttributeMappingCanBeDisabled()
    {
        $methods = $this->methodCalls(['enable_attributes' => false]);

        $this->assertNotContains('enableAttributeMapping', $methods);
    }

    public function testMultipleStaticMethods()
    {
        $calls = $this->load(['static_method' => ['loadFoo', 'loadBar']])->getDefinition('validator.builder')->getMethodCalls();
        $calls = array_values(array_filter($calls, static fn ($call) => 'addMethodMapping' === $call[0]));

        $this->assertSame([['loadFoo'], ['loadBar']], array_column($calls, 1));
    }

    public function testNoStaticMethod()
    {
        $this->assertNotContains('addMethodMapping', $this->methodCalls(['static_method' => false]));
    }

    public function testTheMappingCacheIsOnlyUsedOutsideDebugMode()
    {
        $this->assertContains('setMappingCache', $this->methodCalls([]));
        $this->assertNotContains('setMappingCache', $this->methodCalls([], debug: true));
    }

    public function testTranslationCanBeDisabled()
    {
        $this->assertNotContains('disableTranslation', $this->methodCalls([]));
        $this->assertContains('disableTranslation', $this->methodCalls(['disable_translation' => true]));
    }

    public function testPropertyMetadataExistenceCheck()
    {
        $this->assertNotContains('enablePropertyMetadataExistenceCheck', $this->methodCalls([]));
        $this->assertContains('enablePropertyMetadataExistenceCheck', $this->methodCalls(['property_metadata_existence_check' => true]));
    }

    #[DataProvider('provideEmailValidationModes')]
    public function testEmailValidationModeIsPassedToEmailValidator(string $mode)
    {
        $container = $this->load(['email_validation_mode' => $mode]);

        $this->assertSame($mode, $container->getDefinition('validator.email')->getArgument(0));
    }

    public static function provideEmailValidationModes(): iterable
    {
        foreach (Email::VALIDATION_MODES as $mode) {
            yield [$mode];
        }
    }

    public function testTranslationDomain()
    {
        $container = $this->load(['translation_domain' => 'messages']);

        $this->assertSame('messages', $container->getParameter('validator.translation_domain'));
    }

    public function testNotCompromisedPassword()
    {
        $definition = $this->load(['not_compromised_password' => ['enabled' => false, 'endpoint' => 'https://example.com']])->getDefinition('validator.not_compromised_password');

        $this->assertFalse($definition->getArgument(2));
        $this->assertSame('https://example.com', $definition->getArgument(3));
    }

    public function testAutoMapping()
    {
        $container = $this->load(['auto_mapping' => ['App\\' => ['foo', 'bar'], 'Symfony\\' => ['a', 'b'], 'Foo\\']]);

        $this->assertSame([
            'App\\' => ['services' => ['foo', 'bar']],
            'Symfony\\' => ['services' => ['a', 'b']],
            'Foo\\' => ['services' => []],
        ], $container->getParameter('validator.auto_mapping'));
        $this->assertTrue($container->hasDefinition('validator.property_info_loader'));
    }

    public function testMappingPaths()
    {
        $dir = __DIR__.'/Fixtures/validation_mapping';
        $calls = $this->load(['mapping' => ['paths' => [$dir]]])->getDefinition('validator.builder')->getMethodCalls();

        $xmlMappings = array_column(array_filter($calls, static fn ($call) => 'addXmlMappings' === $call[0]), 1);
        $yamlMappings = array_column(array_filter($calls, static fn ($call) => 'addYamlMappings' === $call[0]), 1);

        // the finder reports the paths with the separator of the platform
        $this->assertSame([[[strtr($dir.'/validation.xml', '/', \DIRECTORY_SEPARATOR)]]], $xmlMappings);
        $this->assertSame([[[strtr($dir.'/validation.yml', '/', \DIRECTORY_SEPARATOR)]]], $yamlMappings);
    }

    public function testMappingPathsContainingAPercentSign()
    {
        // two percent signs are required: "%2Fother%" is what the parameter bag reads as a reference
        $projectDir = str_replace('\\', '/', sys_get_temp_dir()).'/sf_validator_my%2Fother%2Fbranch_'.substr(md5(__METHOD__), 0, 8);
        @mkdir($projectDir.'/config/validator', 0o777, true);
        copy(__DIR__.'/Fixtures/validation_mapping/validation.xml', $projectDir.'/config/validator/validation.xml');

        try {
            $calls = $this->load([], projectDir: $projectDir)->getDefinition('validator.builder')->getMethodCalls();
            $xmlMappings = array_column(array_filter($calls, static fn ($call) => 'addXmlMappings' === $call[0]), 1);
            $files = array_map(static fn ($file) => str_replace(['%%', '\\'], ['%', '/'], $file), $xmlMappings[0][0]);

            $this->assertContains($projectDir.'/config/validator/validation.xml', $files);
        } finally {
            @unlink($projectDir.'/config/validator/validation.xml');
            @rmdir($projectDir.'/config/validator');
            @rmdir($projectDir.'/config');
            @rmdir($projectDir);
        }
    }

    public function testAnUnsupportedMappingPathIsRejected()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not open file or directory "/nowhere.yml".');

        $this->load(['mapping' => ['paths' => ['/nowhere.yml']]]);
    }

    public function testCachePoolsFollowTheCacheSystem()
    {
        $container = $this->load([]);
        $this->assertTrue($container->has('cache.validator'));
        $this->assertTrue($container->has('validator.mapping.cache.adapter'));

        $container = $this->load([], cache: false);
        $this->assertFalse($container->has('cache.validator'));
        $this->assertFalse($container->has('validator.mapping.cache.adapter'));
        $this->assertNotContains('setMappingCache', array_column($container->getDefinition('validator.builder')->getMethodCalls(), 0));
    }

    public function testDataCollectorFollowsTheProfilerInDebugMode()
    {
        $container = $this->load([], debug: true, profiler: true);
        $this->assertTrue($container->hasDefinition('data_collector.validator'));
        $this->assertTrue($container->hasDefinition('debug.validator'));

        $this->assertFalse($this->load([], debug: true)->hasDefinition('data_collector.validator'));
        $this->assertFalse($this->load([], profiler: true)->hasDefinition('data_collector.validator'));
    }

    public function testValidatorService()
    {
        $container = $this->load([], merge: false);
        $container->setAlias('validator.alias', 'validator')->setPublic(true);
        $container->compile();

        $this->assertInstanceOf(ValidatorInterface::class, $container->get('validator.alias'));
    }

    /**
     * @return list<string>
     */
    private function methodCalls(array $config, bool $debug = false): array
    {
        return array_column($this->load($config, $debug)->getDefinition('validator.builder')->getMethodCalls(), 0);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $debug = false, bool $merge = true, bool $cache = true, bool $profiler = false, ?string $projectDir = null): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'kernel.debug' => $debug,
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.project_dir' => str_replace('%', '%%', $projectDir ?? __DIR__.'/Fixtures'),
            'kernel.charset' => 'UTF-8',
            'kernel.enabled_locales' => [],
            'kernel.bundles_metadata' => [],
            'kernel.container_class' => 'TestContainer',
        ]));

        // what the neighbouring bundles would have registered
        $container->register('property_info', PropertyInfoExtractor::class);

        if ($cache) {
            $container->register('cache.system', ArrayAdapter::class);
        }

        if ($profiler) {
            $container->register('profiler', \stdClass::class);
        }

        $bundle = new ValidationBundle();
        $bundle->build($container);
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('validation', $config);

        if (!$merge) {
            // the caller compiles, which runs the passes build() registered
            $container->getCompilerPassConfig()->setRemovingPasses([]);

            return $container;
        }

        new MergeExtensionConfigurationPass()->process($container);
        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);

        return $container;
    }
}
