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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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
        $this->assertEquals('s3cr3t', $container->getParameterBag()->resolveValue($container->findDefinition('form.csrf_provider')->getArgument(1)));
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

    public function testTemplating()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('templating.name_parser'), '->registerTemplatingConfiguration() loads templating.xml');

        $this->assertEquals('request', $container->getDefinition('templating.helper.assets')->getScope(), '->registerTemplatingConfiguration() sets request scope on assets helper if one or more packages are request-scoped');

        // default package should have one HTTP base URL and path package SSL URL
        $this->assertTrue($container->hasDefinition('templating.asset.default_package.http'));
        $package = $container->getDefinition('templating.asset.default_package.http');
        $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\DefinitionDecorator', $package);
        $this->assertEquals('templating.asset.url_package', $package->getParent());
        $arguments = array_values($package->getArguments());
        $this->assertEquals(array('http://cdn.example.com'), $arguments[0]);
        $this->assertEquals('SomeVersionScheme', $arguments[1]);
        $this->assertEquals('%%s?%%s', $arguments[2]);

        $this->assertTrue($container->hasDefinition('templating.asset.default_package.ssl'));
        $package = $container->getDefinition('templating.asset.default_package.ssl');
        $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\DefinitionDecorator', $package);
        $this->assertEquals('templating.asset.path_package', $package->getParent());

        $this->assertEquals('templating.engine.delegating', (string) $container->getAlias('templating'), '->registerTemplatingConfiguration() configures delegating loader if multiple engines are provided');

        $this->assertEquals($container->getDefinition('templating.loader.chain'), $container->getDefinition('templating.loader.wrapped'), '->registerTemplatingConfiguration() configures loader chain if multiple loaders are provided');

        $this->assertEquals($container->getDefinition('templating.loader'), $container->getDefinition('templating.loader.cache'), '->registerTemplatingConfiguration() configures the loader to use cache');

        $this->assertEquals('%templating.loader.cache.path%', $container->getDefinition('templating.loader.cache')->getArgument(1));
        $this->assertEquals('/path/to/cache', $container->getParameter('templating.loader.cache.path'));

        $this->assertEquals(array('php', 'twig'), $container->getParameter('templating.engines'), '->registerTemplatingConfiguration() sets a templating.engines parameter');

        $this->assertEquals(array('FrameworkBundle:Form', 'theme1', 'theme2'), $container->getParameter('templating.helper.form.resources'), '->registerTemplatingConfiguration() registers the theme and adds the base theme');
        $this->assertEquals('global_hinclude_template', $container->getParameter('fragment.renderer.hinclude.global_template'), '->registerTemplatingConfiguration() registers the global hinclude.js template');
    }

    public function testTemplatingAssetsHelperScopeDependsOnPackageArgumentScopes()
    {
        $container = $this->createContainerFromFile('templating_url_package');

        $this->assertNotEquals('request', $container->getDefinition('templating.helper.assets')->getScope(), '->registerTemplatingConfiguration() does not set request scope on assets helper if no packages are request-scoped');
    }

    public function testTranslator()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('translator.default'), '->registerTranslatorConfiguration() loads translation.xml');
        $this->assertEquals('translator.default', (string) $container->getAlias('translator'), '->registerTranslatorConfiguration() redefines translator service from identity to real translator');

        $resources = array();
        foreach ($container->getDefinition('translator.default')->getMethodCalls() as $call) {
            if ('addResource' == $call[0]) {
                $resources[] = $call[1];
            }
        }

        $files = array_map(function ($resource) { return realpath($resource[1]); }, $resources);
        $ref = new \ReflectionClass('Symfony\Component\Validator\Validator');
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
        $ref = new \ReflectionClass('Symfony\Component\Security\Core\SecurityContext');
        $this->assertContains(
            strtr(dirname(dirname($ref->getFileName())).'/Resources/translations/security.en.xlf', '/', DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Security translation resources'
        );

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        $this->assertEquals(array('fr'), $calls[0][1][0]);
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

        $this->assertTrue($container->hasDefinition('validator'), '->registerValidationConfiguration() loads validator.xml');
        $this->assertTrue($container->hasDefinition('validator.mapping.loader.xml_files_loader'), '->registerValidationConfiguration() defines the XML loader');
        $this->assertTrue($container->hasDefinition('validator.mapping.loader.yaml_files_loader'), '->registerValidationConfiguration() defines the YAML loader');

        $xmlFiles = $container->getParameter('validator.mapping.loader.xml_files_loader.mapping_files');
        $ref = new \ReflectionClass('Symfony\Component\Form\Form');
        $this->assertContains(
            realpath(dirname($ref->getFileName()).'/Resources/config/validation.xml'),
            array_map('realpath', $xmlFiles),
            '->registerValidationConfiguration() adds Form validation.xml to XML loader'
        );
    }

    public function testAnnotations()
    {
        if (!class_exists('Doctrine\\Common\\Version')) {
            $this->markTestSkipped('Doctrine is not available.');
        }

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

        $this->assertTrue($container->hasDefinition('validator.mapping.loader.annotation_loader'), '->registerValidationConfiguration() defines the annotation loader');
        $loaders = $container->getDefinition('validator.mapping.loader.loader_chain')->getArgument(0);
        $found = false;
        foreach ($loaders as $loader) {
            if ('validator.mapping.loader.annotation_loader' === (string) $loader) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'validator.mapping.loader.annotation_loader is added to the loader chain.');
    }

    public function testValidationPaths()
    {
        require_once __DIR__."/Fixtures/TestBundle/TestBundle.php";

        $container = $this->createContainerFromFile('validation_annotations', array(
            'kernel.bundles' => array('TestBundle' => 'Symfony\Bundle\FrameworkBundle\Tests\TestBundle'),
        ));

        $yamlArgs = $container->getParameter('validator.mapping.loader.yaml_files_loader.mapping_files');
        $this->assertCount(1, $yamlArgs);
        $this->assertStringEndsWith('TestBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'validation.yml', $yamlArgs[0]);

        $xmlArgs = $container->getParameter('validator.mapping.loader.xml_files_loader.mapping_files');
        $this->assertCount(2, $xmlArgs);
        $this->assertStringEndsWith('Component'.DIRECTORY_SEPARATOR.'Form/Resources/config/validation.xml', $xmlArgs[0]);
        $this->assertStringEndsWith('TestBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'validation.xml', $xmlArgs[1]);
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
}
