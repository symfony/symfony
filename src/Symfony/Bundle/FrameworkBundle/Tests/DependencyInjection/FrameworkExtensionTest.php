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

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\Validation;

abstract class FrameworkExtensionTest extends TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testCsrfProtection()
    {
        $container = $this->createContainerFromFile('full');

        $def = $container->getDefinition('form.type_extension.csrf');

        $this->assertTrue($container->getParameter('form.type_extension.csrf.enabled'));
        $this->assertEquals('%form.type_extension.csrf.enabled%', $def->getArgument(1));
        $this->assertEquals('_csrf', $container->getParameter('form.type_extension.csrf.field_name'));
        $this->assertEquals('%form.type_extension.csrf.field_name%', $def->getArgument(2));
    }

    public function testPropertyAccessWithDefaultValue()
    {
        $container = $this->createContainerFromFile('full');

        $def = $container->getDefinition('property_accessor');
        $this->assertFalse($def->getArgument(0));
        $this->assertFalse($def->getArgument(1));
    }

    public function testPropertyAccessWithOverriddenValues()
    {
        $container = $this->createContainerFromFile('property_accessor');
        $def = $container->getDefinition('property_accessor');
        $this->assertTrue($def->getArgument(0));
        $this->assertTrue($def->getArgument(1));
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage CSRF protection needs sessions to be enabled.
     */
    public function testCsrfProtectionNeedsSessionToBeEnabled()
    {
        $this->createContainerFromFile('csrf_needs_session');
    }

    public function testCsrfProtectionForFormsEnablesCsrfProtectionAutomatically()
    {
        $container = $this->createContainerFromFile('csrf');

        $this->assertTrue($container->hasDefinition('security.csrf.token_manager'));
    }

    public function testSecureRandomIsAvailableIfCsrfIsDisabled()
    {
        $container = $this->createContainerFromFile('csrf_disabled');

        $this->assertTrue($container->hasDefinition('security.secure_random'));
    }

    public function testProxies()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertEquals(array('127.0.0.1', '10.0.0.1'), $container->getParameter('kernel.trusted_proxies'));
    }

    public function testHttpMethodOverride()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertFalse($container->getParameter('kernel.http_method_override'));
    }

    public function testEsi()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('esi'), '->registerEsiConfiguration() loads esi.xml');
    }

    public function testEnabledProfiler()
    {
        $container = $this->createContainerFromFile('profiler');

        $this->assertTrue($container->hasDefinition('profiler'), '->registerProfilerConfiguration() loads profiling.xml');
        $this->assertTrue($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() loads collectors.xml');
    }

    public function testDisabledProfiler()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertFalse($container->hasDefinition('profiler'), '->registerProfilerConfiguration() does not load profiling.xml');
        $this->assertFalse($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() does not load collectors.xml');
    }

    public function testRouter()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->has('router'), '->registerRouterConfiguration() loads routing.xml');
        $arguments = $container->findDefinition('router')->getArguments();
        $this->assertEquals($container->getParameter('kernel.root_dir').'/config/routing.xml', $container->getParameter('router.resource'), '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('%router.resource%', $arguments[1], '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('xml', $arguments[2]['resource_type'], '->registerRouterConfiguration() sets routing resource type');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testRouterRequiresResourceOption()
    {
        $container = $this->createContainer();
        $loader = new FrameworkExtension();
        $loader->load(array(array('router' => true)), $container);
    }

    public function testSession()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('session'), '->registerSessionConfiguration() loads session.xml');
        $this->assertEquals('fr', $container->getParameter('kernel.default_locale'));
        $this->assertEquals('session.storage.native', (string) $container->getAlias('session.storage'));
        $this->assertEquals('session.handler.native_file', (string) $container->getAlias('session.handler'));

        $options = $container->getParameter('session.storage.options');
        $this->assertEquals('_SYMFONY', $options['name']);
        $this->assertEquals(86400, $options['cookie_lifetime']);
        $this->assertEquals('/', $options['cookie_path']);
        $this->assertEquals('example.com', $options['cookie_domain']);
        $this->assertTrue($options['cookie_secure']);
        $this->assertTrue($options['cookie_httponly']);
        $this->assertEquals(108, $options['gc_divisor']);
        $this->assertEquals(1, $options['gc_probability']);
        $this->assertEquals(90000, $options['gc_maxlifetime']);

        $this->assertEquals('/path/to/sessions', $container->getParameter('session.save_path'));
    }

    public function testNullSessionHandler()
    {
        $container = $this->createContainerFromFile('session');

        $this->assertTrue($container->hasDefinition('session'), '->registerSessionConfiguration() loads session.xml');
        $this->assertNull($container->getDefinition('session.storage.native')->getArgument(1));
        $this->assertNull($container->getDefinition('session.storage.php_bridge')->getArgument(0));
    }

    public function testRequest()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('request.add_request_formats_listener'), '->registerRequestConfiguration() loads request.xml');
        $listenerDef = $container->getDefinition('request.add_request_formats_listener');
        $this->assertEquals(array('csv' => array('text/csv', 'text/plain'), 'pdf' => array('application/pdf')), $listenerDef->getArgument(0));
    }

    public function testEmptyRequestFormats()
    {
        $container = $this->createContainerFromFile('request');

        $this->assertFalse($container->hasDefinition('request.add_request_formats_listener'), '->registerRequestConfiguration() does not load request.xml when no request formats are defined');
    }

    public function testTemplating()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('templating.name_parser'), '->registerTemplatingConfiguration() loads templating.xml');

        $this->assertEquals('templating.engine.delegating', (string) $container->getAlias('templating'), '->registerTemplatingConfiguration() configures delegating loader if multiple engines are provided');

        $this->assertEquals($container->getDefinition('templating.loader.chain'), $container->getDefinition('templating.loader.wrapped'), '->registerTemplatingConfiguration() configures loader chain if multiple loaders are provided');

        $this->assertEquals($container->getDefinition('templating.loader'), $container->getDefinition('templating.loader.cache'), '->registerTemplatingConfiguration() configures the loader to use cache');

        $this->assertEquals('%templating.loader.cache.path%', $container->getDefinition('templating.loader.cache')->getArgument(1));
        $this->assertEquals('/path/to/cache', $container->getParameter('templating.loader.cache.path'));

        $this->assertEquals(array('php', 'twig'), $container->getParameter('templating.engines'), '->registerTemplatingConfiguration() sets a templating.engines parameter');

        $this->assertEquals(array('FrameworkBundle:Form', 'theme1', 'theme2'), $container->getParameter('templating.helper.form.resources'), '->registerTemplatingConfiguration() registers the theme and adds the base theme');
        $this->assertEquals('global_hinclude_template', $container->getParameter('fragment.renderer.hinclude.global_template'), '->registerTemplatingConfiguration() registers the global hinclude.js template');
    }

    /**
     * @group legacy
     */
    public function testLegacyTemplatingAssets()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $this->checkAssetsPackages($this->createContainerFromFile('legacy_templating_assets'), true);
    }

    public function testAssets()
    {
        $this->checkAssetsPackages($this->createContainerFromFile('assets'));
    }

    public function testTranslator()
    {
        $container = $this->createContainerFromFile('full');
        $this->assertTrue($container->hasDefinition('translator.default'), '->registerTranslatorConfiguration() loads translation.xml');
        $this->assertEquals('translator.default', (string) $container->getAlias('translator'), '->registerTranslatorConfiguration() redefines translator service from identity to real translator');
        $options = $container->getDefinition('translator.default')->getArgument(3);

        $files = array_map(function ($resource) { return realpath($resource); }, $options['resource_files']['en']);
        $ref = new \ReflectionClass('Symfony\Component\Validator\Validation');
        $this->assertContains(
            strtr(dirname($ref->getFileName()).'/Resources/translations/validators.en.xlf', '/', DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Validator translation resources'
        );
        $ref = new \ReflectionClass('Symfony\Component\Form\Form');
        $this->assertContains(
            strtr(dirname($ref->getFileName()).'/Resources/translations/validators.en.xlf', '/', DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Form translation resources'
        );
        $ref = new \ReflectionClass('Symfony\Component\Security\Core\Security');
        $this->assertContains(
            strtr(dirname($ref->getFileName()).'/Resources/translations/security.en.xlf', '/', DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Security translation resources'
        );

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        $this->assertEquals(array('fr'), $calls[0][1][0]);
    }

    public function testTranslatorMultipleFallbacks()
    {
        $container = $this->createContainerFromFile('translator_fallbacks');

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        $this->assertEquals(array('en', 'fr'), $calls[0][1][0]);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testTemplatingRequiresAtLeastOneEngine()
    {
        $container = $this->createContainer();
        $loader = new FrameworkExtension();
        $loader->load(array(array('templating' => null)), $container);
    }

    public function testValidation()
    {
        $container = $this->createContainerFromFile('full');

        $ref = new \ReflectionClass('Symfony\Component\Form\Form');
        $xmlMappings = array(realpath(dirname($ref->getFileName()).'/Resources/config/validation.xml'));

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(6, $calls);
        $this->assertSame('setConstraintValidatorFactory', $calls[0][0]);
        $this->assertEquals(array(new Reference('validator.validator_factory')), $calls[0][1]);
        $this->assertSame('setTranslator', $calls[1][0]);
        $this->assertEquals(array(new Reference('translator')), $calls[1][1]);
        $this->assertSame('setTranslationDomain', $calls[2][0]);
        $this->assertSame(array('%validator.translation_domain%'), $calls[2][1]);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $this->assertSame(array($xmlMappings), $calls[3][1]);
        $this->assertSame('addMethodMapping', $calls[4][0]);
        $this->assertSame(array('loadValidatorMetadata'), $calls[4][1]);
        $this->assertSame('setMetadataCache', $calls[5][0]);
        $this->assertEquals(array(new Reference('validator.mapping.cache.apc')), $calls[5][1]);
    }

    /**
     * @group legacy
     */
    public function testLegacyFullyConfiguredValidationService()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        if (!extension_loaded('apc')) {
            $this->markTestSkipped('The apc extension is not available.');
        }

        $container = $this->createContainerFromFile('full');

        $this->assertInstanceOf('Symfony\Component\Validator\Validator\ValidatorInterface', $container->get('validator'));
    }

    public function testValidationService()
    {
        $container = $this->createContainerFromFile('validation_annotations');

        $this->assertInstanceOf('Symfony\Component\Validator\Validator\ValidatorInterface', $container->get('validator'));
    }

    public function testAnnotations()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertEquals($container->getParameter('kernel.cache_dir').'/annotations', $container->getDefinition('annotations.file_cache_reader')->getArgument(1));
        $this->assertInstanceOf('Doctrine\Common\Annotations\FileCacheReader', $container->get('annotation_reader'));
    }

    public function testFileLinkFormat()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertEquals('file%link%format', $container->getParameter('templating.helper.code.file_link_format'));
    }

    public function testValidationAnnotations()
    {
        $container = $this->createContainerFromFile('validation_annotations');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(6, $calls);
        $this->assertSame('enableAnnotationMapping', $calls[4][0]);
        $this->assertEquals(array(new Reference('annotation_reader')), $calls[4][1]);
        $this->assertSame('addMethodMapping', $calls[5][0]);
        $this->assertSame(array('loadValidatorMetadata'), $calls[5][1]);
        // no cache this time
    }

    public function testValidationPaths()
    {
        require_once __DIR__.'/Fixtures/TestBundle/TestBundle.php';

        $container = $this->createContainerFromFile('validation_annotations', array(
            'kernel.bundles' => array('TestBundle' => 'Symfony\Bundle\FrameworkBundle\Tests\TestBundle'),
        ));

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(7, $calls);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $this->assertSame('addYamlMappings', $calls[4][0]);
        $this->assertSame('enableAnnotationMapping', $calls[5][0]);
        $this->assertSame('addMethodMapping', $calls[6][0]);
        $this->assertSame(array('loadValidatorMetadata'), $calls[6][1]);

        $xmlMappings = $calls[3][1][0];
        $this->assertCount(2, $xmlMappings);
        $this->assertStringEndsWith('Component'.DIRECTORY_SEPARATOR.'Form/Resources/config/validation.xml', $xmlMappings[0]);
        $this->assertStringEndsWith('TestBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'validation.xml', $xmlMappings[1]);

        $yamlMappings = $calls[4][1][0];
        $this->assertCount(1, $yamlMappings);
        $this->assertStringEndsWith('TestBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'validation.yml', $yamlMappings[0]);
    }

    public function testValidationNoStaticMethod()
    {
        $container = $this->createContainerFromFile('validation_no_static_method');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(4, $calls);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        // no cache, no annotations, no static methods
    }

    public function testFormsCanBeEnabledWithoutCsrfProtection()
    {
        $container = $this->createContainerFromFile('form_no_csrf');

        $this->assertFalse($container->getParameter('form.type_extension.csrf.enabled'));
    }

    /**
     * @group legacy
     */
    public function testLegacyFormCsrfFieldNameCanBeSetUnderCsrfSettings()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $container = $this->createContainerFromFile('form_csrf_sets_field_name');

        $this->assertTrue($container->getParameter('form.type_extension.csrf.enabled'));
        $this->assertEquals('_custom', $container->getParameter('form.type_extension.csrf.field_name'));
    }

    /**
     * @group legacy
     */
    public function testLegacyFormCsrfFieldNameUnderFormSettingsTakesPrecedence()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $container = $this->createContainerFromFile('form_csrf_under_form_sets_field_name');

        $this->assertTrue($container->getParameter('form.type_extension.csrf.enabled'));
        $this->assertEquals('_custom_form', $container->getParameter('form.type_extension.csrf.field_name'));
    }

    public function testStopwatchEnabledWithDebugModeEnabled()
    {
        $container = $this->createContainerFromFile('default_config', array(
            'kernel.container_class' => 'foo',
            'kernel.debug' => true,
        ));

        $this->assertTrue($container->has('debug.stopwatch'));
    }

    public function testStopwatchEnabledWithDebugModeDisabled()
    {
        $container = $this->createContainerFromFile('default_config', array(
            'kernel.container_class' => 'foo',
        ));

        $this->assertTrue($container->has('debug.stopwatch'));
    }

    public function testSerializerDisabled()
    {
        $container = $this->createContainerFromFile('default_config');
        $this->assertFalse($container->has('serializer'));
    }

    public function testSerializerEnabled()
    {
        $container = $this->createContainerFromFile('full');
        $this->assertTrue($container->has('serializer'));
    }

    public function testAssetHelperWhenAssetsAreEnabled()
    {
        $container = $this->createContainerFromFile('full');
        $packages = $container->getDefinition('templating.helper.assets')->getArgument(0);

        $this->assertSame('assets.packages', (string) $packages);
    }

    public function testAssetHelperWhenTemplatesAreEnabledAndAssetsAreDisabled()
    {
        $container = $this->createContainerFromFile('assets_disabled');
        $packages = $container->getDefinition('templating.helper.assets')->getArgument(0);

        $this->assertSame('assets.packages', (string) $packages);
    }

    protected function createContainer(array $data = array())
    {
        return new ContainerBuilder(new ParameterBag(array_merge(array(
            'kernel.bundles' => array('FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'),
            'kernel.cache_dir' => __DIR__,
            'kernel.debug' => false,
            'kernel.environment' => 'test',
            'kernel.name' => 'kernel',
            'kernel.root_dir' => __DIR__,
        ), $data)));
    }

    protected function createContainerFromFile($file, $data = array())
    {
        $container = $this->createContainer($data);
        $container->registerExtension(new FrameworkExtension());
        $this->loadFromFile($container, $file);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        return $container;
    }

    protected function createContainerFromClosure($closure, $data = array())
    {
        $container = $this->createContainer($data);
        $container->registerExtension(new FrameworkExtension());
        $loader = new ClosureLoader($container);
        $loader->load($closure);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        return $container;
    }

    private function checkAssetsPackages(ContainerBuilder $container, $legacy = false)
    {
        $packages = $container->getDefinition('assets.packages');

        // default package
        $defaultPackage = $container->getDefinition($packages->getArgument(0));
        $this->assertUrlPackage($container, $defaultPackage, array('http://cdn.example.com'), 'SomeVersionScheme', '%%s?version=%%s');

        // packages
        $packages = $packages->getArgument(1);
        $this->assertCount($legacy ? 3 : 4, $packages);

        if (!$legacy) {
            $package = $container->getDefinition($packages['images_path']);
            $this->assertPathPackage($container, $package, '/foo', 'SomeVersionScheme', '%%s?version=%%s');
        }

        $package = $container->getDefinition($packages['images']);
        $this->assertUrlPackage($container, $package, array('http://images1.example.com', 'http://images2.example.com'), '1.0.0', $legacy ? '%%s?%%s' : '%%s?version=%%s');

        $package = $container->getDefinition($packages['foo']);
        $this->assertPathPackage($container, $package, '', '1.0.0', '%%s-%%s');

        $package = $container->getDefinition($packages['bar']);
        $this->assertUrlPackage($container, $package, array('https://bar2.example.com'), $legacy ? '' : 'SomeVersionScheme', $legacy ? '%%s?%%s' : '%%s?version=%%s');
    }

    private function assertPathPackage(ContainerBuilder $container, Definition $package, $basePath, $version, $format)
    {
        $this->assertEquals('assets.path_package', $package->getParent());
        $this->assertEquals($basePath, $package->getArgument(0));
        $this->assertVersionStrategy($container, $package->getArgument(1), $version, $format);
    }

    private function assertUrlPackage(ContainerBuilder $container, Definition $package, $baseUrls, $version, $format)
    {
        $this->assertEquals('assets.url_package', $package->getParent());
        $this->assertEquals($baseUrls, $package->getArgument(0));
        $this->assertVersionStrategy($container, $package->getArgument(1), $version, $format);
    }

    private function assertVersionStrategy(ContainerBuilder $container, Reference $reference, $version, $format)
    {
        $versionStrategy = $container->getDefinition($reference);
        if (null === $version) {
            $this->assertEquals('assets.empty_version_strategy', (string) $reference);
        } else {
            $this->assertEquals('assets.static_version_strategy', $versionStrategy->getParent());
            $this->assertEquals($version, $versionStrategy->getArgument(0));
            $this->assertEquals($format, $versionStrategy->getArgument(1));
        }
    }
}
